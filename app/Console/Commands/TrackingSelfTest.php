<?php

namespace App\Console\Commands;

use App\Models\TrackingDestination;
use App\Services\Tracking\TrackingConsentService;
use App\Services\Tracking\TrackingEventService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

class TrackingSelfTest extends Command
{
    protected $signature = 'tracking:self-test';

    protected $description = 'Run all nine tracking event paths locally in a rollback-only transaction without provider calls';

    public function handle(TrackingConsentService $consents, TrackingEventService $events): int
    {
        $this->info('Running isolated Tracking & AI pipeline self-test...');
        $this->line('Synthetic events only. Database writes are rolled back; queue jobs and external HTTP calls are blocked.');

        $connection = DB::connection();
        $transactionLevel = $connection->transactionLevel();
        $queueConnection = config('queue.default');
        $eventIds = [];
        $failures = [];
        $rolledBack = false;
        $consentToken = Str::random(64);

        try {
            Http::fake();
            Queue::fake();
            config(['queue.default' => 'sync']);
            $connection->beginTransaction();

            $request = Request::create('https://bestcool.test/tracking-self-test', 'POST', [], [
                TrackingConsentService::COOKIE => $consentToken,
            ]);
            $session = app('session')->driver();
            $session->start();
            $request->setLaravelSession($session);
            $consents->remember($consentToken, true, true, $session->getId());

            $testCases = [
                'PageView' => ['channel' => 'browser', 'properties' => ['page_path' => '/tracking-self-test']],
                'ViewContent' => ['channel' => 'browser', 'properties' => ['page_path' => '/tracking-self-test', 'product_id' => 'SELFTEST-PRODUCT', 'product_sku' => 'SELFTEST-SKU', 'value' => 1, 'currency' => 'BDT']],
                'AddToCart' => ['channel' => 'server', 'properties' => ['page_path' => '/tracking-self-test', 'product_id' => 'SELFTEST-PRODUCT', 'product_sku' => 'SELFTEST-SKU', 'quantity' => 1, 'value' => 1, 'currency' => 'BDT']],
                'InitiateCheckout' => ['channel' => 'server', 'properties' => ['page_path' => '/tracking-self-test', 'value' => 1, 'currency' => 'BDT']],
                'Purchase' => ['channel' => 'server', 'properties' => ['page_path' => '/tracking-self-test', 'value' => 1, 'currency' => 'BDT']],
                'LinkClick' => ['channel' => 'browser', 'properties' => ['page_path' => '/tracking-self-test', 'link_url' => 'https://example.invalid/tracking-self-test', 'link_label' => 'Synthetic self-test']],
                'PhoneClick' => ['channel' => 'browser', 'properties' => ['page_path' => '/tracking-self-test', 'link_url' => 'tel:[redacted]', 'link_label' => 'Synthetic self-test']],
                'EmailClick' => ['channel' => 'browser', 'properties' => ['page_path' => '/tracking-self-test', 'link_url' => 'mailto:[redacted]', 'link_label' => 'Synthetic self-test']],
                'Search' => ['channel' => 'browser', 'properties' => ['page_path' => '/tracking-self-test', 'search_term' => 'tracking self test', 'result_count' => 0]],
            ];

            $aggregateBefore = (int) DB::table('tracking_daily_aggregates')->sum('event_count');
            foreach ($testCases as $name => $case) {
                $eventId = (string) Str::uuid();
                $eventIds[] = $eventId;
                $captured = $case['channel'] === 'server'
                    ? $events->captureTrusted($request, $name, $case['properties'], $eventId)
                    : $events->captureBrowser($request, $name, $eventId, $case['properties']);

                if (! $captured) {
                    $failures[] = $name.' was not accepted by the canonical collector.';
                    $this->error('FAIL '.$name.' ('.$case['channel'].')');
                    continue;
                }

                if (in_array($name, ['AddToCart', 'InitiateCheckout'], true)) {
                    $browserObservation = $events->captureBrowser($request, $name, $eventId, $case['properties']);
                    if (! $browserObservation || $browserObservation->id !== $captured->id) {
                        $failures[] = $name.' browser/server observations did not deduplicate.';
                        $this->error('FAIL '.$name.' browser/server deduplication');
                        continue;
                    }
                    $this->line('PASS '.$name.' (server + browser observation, one canonical event)');
                } else {
                    $this->line('PASS '.$name.' ('.$case['channel'].')');
                }
            }

            $canonicalCount = DB::table('tracking_events')->whereIn('event_id', $eventIds)->count();
            $observationCount = DB::table('tracking_event_observations')->whereIn('event_id', $eventIds)->count();
            $aggregateIncrease = (int) DB::table('tracking_daily_aggregates')->sum('event_count') - $aggregateBefore;
            $stagedDeliveries = DB::table('tracking_deliveries')
                ->join('tracking_events', 'tracking_events.id', '=', 'tracking_deliveries.tracking_event_id')
                ->whereIn('tracking_events.event_id', $eventIds)->count();

            if ($canonicalCount !== 9) $failures[] = "Expected 9 canonical rows inside the transaction; found {$canonicalCount}.";
            if ($observationCount !== 11) $failures[] = "Expected 11 correlated source observations; found {$observationCount}.";
            if ($aggregateIncrease !== 9) $failures[] = "Expected 9 temporary aggregate increments; found {$aggregateIncrease}.";

            Http::assertNothingSent();
            Queue::assertNothingPushed();
            $this->line("PASS canonical events: {$canonicalCount}; source observations: {$observationCount}; aggregate increments: {$aggregateIncrease}");
            $this->line("PASS provider deliveries staged then rolled back: {$stagedDeliveries}");
            $this->line('PASS provider HTTP calls: 0; queue jobs dispatched: 0');
        } catch (Throwable $exception) {
            $failures[] = 'Self-test stopped with '.$exception::class.'.';
        } finally {
            while ($connection->transactionLevel() > $transactionLevel) {
                $connection->rollBack();
            }
            config(['queue.default' => $queueConnection]);
            $rolledBack = ! DB::table('tracking_events')->whereIn('event_id', $eventIds)->exists()
                && ! DB::table('tracking_consents')->where('visitor_hash', $consents->visitorHash($consentToken))->exists();
        }

        if (! $rolledBack) $failures[] = 'Rollback verification failed; inspect the tracking tables before retrying.';
        if ($rolledBack) $this->info('PASS rollback verified: no synthetic event or consent data remains.');

        $this->newLine();
        $this->line('Provider configuration (credentials are never displayed):');
        foreach (TrackingDestination::query()->with('credentials')->orderBy('code')->get() as $destination) {
            $configured = $destination->credentials !== null;
            $this->line(sprintf(
                '  %s: destination %s, browser %s, server %s, credential %s',
                strtoupper($destination->code),
                $destination->enabled ? 'ON' : 'OFF',
                $destination->browser_enabled ? 'ON' : 'OFF',
                $destination->server_enabled ? 'ON' : 'OFF',
                $configured ? 'configured' : 'missing',
            ));
        }

        if ($failures !== []) {
            foreach ($failures as $failure) $this->error($failure);
            return self::FAILURE;
        }

        $this->info('All nine local event pipeline checks passed.');
        $this->comment('This checks server collection and deduplication. Browser tag firing and provider receipt must be checked separately in a guest browser and provider Test Events/DebugView.');
        return self::SUCCESS;
    }
}
