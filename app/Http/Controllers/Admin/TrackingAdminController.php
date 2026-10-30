<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\DeliverTrackingEvent;
use App\Models\TrackingAiReport;
use App\Models\TrackingConsent;
use App\Models\TrackingDelivery;
use App\Models\TrackingDestination;
use App\Models\TrackingDestinationCredential;
use App\Models\TrackingEvent;
use App\Models\TrackingEventDefinition;
use App\Models\TrackingEventRoute;
use App\Models\TrackingAuditLog;
use App\Models\Product;
use App\Services\Tracking\BusinessIntelligenceService;
use App\Services\Tracking\TestTrackingDestinationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class TrackingAdminController
{
    private const SECTIONS = ['overview', 'configuration', 'routing', 'events', 'analytics', 'links', 'deliveries', 'consent', 'health', 'ai'];

    public function show(Request $request, ?string $section = null): View
    {
        $section = in_array($section, self::SECTIONS, true) ? $section : 'overview';
        $period = $this->period($request);
        $query = TrackingEvent::query()->where('analytics_consent', true)
            ->whereBetween('occurred_at', [$period['from']->utc(), $period['to']->utc()]);
        if (in_array($request->query('source'), ['browser', 'server'], true)) $query->where('source', $request->query('source'));

        $counts = (clone $query)->selectRaw('event_name, count(*) as total')->groupBy('event_name')->pluck('total', 'event_name')->map(fn ($count) => (int) $count)->all();
        $periodDays = max(1, (int) $period['from']->diffInDays($period['to']) + 1);
        $comparisonFrom = (clone $period['from'])->subDays($periodDays);
        $comparisonTo = (clone $period['from'])->subSecond();
        $comparisonQuery = TrackingEvent::query()->where('analytics_consent', true)->whereBetween('occurred_at', [$comparisonFrom->utc(), $comparisonTo->utc()]);
        if (in_array($request->query('source'), ['browser', 'server'], true)) $comparisonQuery->where('source', $request->query('source'));
        $comparisonCounts = $comparisonQuery->selectRaw('event_name, count(*) as total')->groupBy('event_name')->pluck('total', 'event_name')->map(fn ($count) => (int) $count)->all();
        $metrics = [
            'page_views' => $counts['PageView'] ?? 0,
            'product_views' => $counts['ViewContent'] ?? 0,
            'cart_adds' => $counts['AddToCart'] ?? 0,
            'checkouts' => $counts['InitiateCheckout'] ?? 0,
            'orders_placed' => $counts['Purchase'] ?? 0,
            'cod_order_value' => round((float) (clone $query)->where('event_name', 'Purchase')->sum('value'), 2),
            'unique_sessions' => (clone $query)->whereNotNull('session_hash')->distinct('session_hash')->count('session_hash'),
            'link_clicks' => ($counts['LinkClick'] ?? 0) + ($counts['PhoneClick'] ?? 0) + ($counts['EmailClick'] ?? 0),
            'searches' => $counts['Search'] ?? 0,
        ];
        $deliveryQuery = TrackingDelivery::query()->with(['event', 'destination']);
        if (in_array($request->query('delivery_status'), ['pending', 'processing', 'delivered', 'failed'], true)) $deliveryQuery->where('status', $request->query('delivery_status'));
        if (is_numeric($request->query('destination_id'))) $deliveryQuery->where('destination_id', (int) $request->query('destination_id'));
        $deliveries = $deliveryQuery->latest()->paginate(25)->withQueryString();
        $recentEvents = (clone $query)->latest('occurred_at')->paginate(30)->withQueryString();
        $linkQuery = (clone $query)->whereIn('event_name', ['LinkClick', 'PhoneClick', 'EmailClick'])->whereNotNull('link_url');
        if (in_array($request->query('link_type'), ['internal', 'external', 'phone', 'email'], true)) $linkQuery->where('link_type', $request->query('link_type'));
        if (is_string($request->query('link_search')) && trim($request->query('link_search')) !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_substr(trim($request->query('link_search')), 0, 100)).'%';
            $linkQuery->where(fn ($builder) => $builder->where('link_url', 'like', $needle)->orWhere('link_host', 'like', $needle)->orWhere('link_label', 'like', $needle));
        }
        $topLinks = (clone $linkQuery)
            ->selectRaw('event_name, link_type, link_url, link_host, link_label, count(*) as total')
            ->groupBy('event_name', 'link_type', 'link_url', 'link_host', 'link_label')->orderByDesc('total')->limit(50)->get();
        $linkMetrics = [
            'all' => (clone $linkQuery)->count(), 'phone' => (clone $linkQuery)->where('event_name', 'PhoneClick')->count(),
            'email' => (clone $linkQuery)->where('event_name', 'EmailClick')->count(), 'web' => (clone $linkQuery)->where('event_name', 'LinkClick')->count(),
        ];
        $dailyLinkSeries = DB::table('tracking_link_aggregates')->whereBetween('aggregate_date', [$period['from']->toDateString(), $period['to']->toDateString()])
            ->selectRaw('aggregate_date, sum(click_count) as total')->groupBy('aggregate_date')->orderBy('aggregate_date')->get();
        $topSearches = (clone $query)->where('event_name', 'Search')->limit(500)->get(['properties'])
            ->map(fn ($event) => trim(strip_tags((string) ($event->properties['search_term'] ?? ''))))
            ->filter(fn ($term) => $term !== '' && ! str_contains($term, '@') && ! preg_match('/\d{7,}/', $term))
            ->countBy()->sortDesc()->take(12);
        $topProducts = (clone $query)->whereIn('event_name', ['ViewContent', 'AddToCart', 'Purchase'])->whereNotNull('product_id')
            ->selectRaw('product_id, event_name, count(*) as total')->groupBy('product_id', 'event_name')->orderByDesc('total')->limit(15)->get()
            ->map(function ($row) {
                $product = ctype_digit((string) $row->product_id) ? Product::query()->find((int) $row->product_id) : null;
                return ['id' => $row->product_id, 'name' => $product?->name ?? 'Product '.$row->product_id, 'event' => $row->event_name, 'count' => (int) $row->total];
            });
        $dailySeries = DB::table('tracking_daily_aggregates')->whereBetween('aggregate_date', [$period['from']->toDateString(), $period['to']->toDateString()])
            ->selectRaw('aggregate_date, sum(event_count) as total')->groupBy('aggregate_date')->orderBy('aggregate_date')->get();
        $sourceCounts = (clone $query)->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source')->all();
        $observationCounts = DB::table('tracking_event_observations')->join('tracking_events', 'tracking_events.event_id', '=', 'tracking_event_observations.event_id')
            ->where('tracking_events.analytics_consent', true)->whereBetween('tracking_events.occurred_at', [$period['from']->utc(), $period['to']->utc()])
            ->selectRaw('tracking_event_observations.source, count(*) as total')->groupBy('tracking_event_observations.source')->pluck('total', 'source')->all();
        $destinations = TrackingDestination::query()->with('credentials')->orderBy('id')->get()->map(function ($destination) {
            $secrets = $destination->credentials?->secrets ?? [];
            $secretKey = match ($destination->code) { 'meta', 'tiktok' => 'access_token', 'ga4' => 'api_secret', 'openai' => 'api_key', default => null };
            $destination->has_secret = $secretKey ? filled($secrets[$secretKey] ?? null) : false;
            unset($destination->credentials);
            return $destination;
        });
        $definitions = TrackingEventDefinition::query()->with(['routes.destination'])->orderBy('id')->get();
        $routeRows = TrackingEventRoute::query()->with(['definition', 'destination'])->orderBy('event_definition_id')->orderBy('destination_id')->orderBy('channel')->get();
        $consentCounts = [
            'total' => TrackingConsent::query()->count(),
            'analytics' => TrackingConsent::query()->where('analytics', true)->count(),
            'advertising' => TrackingConsent::query()->where('advertising', true)->count(),
        ];
        $pendingCount = TrackingDelivery::query()->whereIn('status', ['pending', 'processing'])->count();
        $failedCount = TrackingDelivery::query()->where('status', 'failed')->count();
        $oldestPending = TrackingDelivery::query()->where('status', 'pending')->oldest('created_at')->value('created_at');
        $latestSuccess = TrackingDelivery::query()->where('status', 'delivered')->latest('last_attempt_at')->first();
        $health = [
            'queue' => config('queue.default'), 'pending' => $pendingCount, 'failed' => $failedCount,
            'oldest_pending_minutes' => $oldestPending ? max(0, now()->diffInMinutes($oldestPending)) : null,
            'latest_success' => $latestSuccess?->last_attempt_at,
            'scheduler_note' => 'A database queue needs a worker or a minute cron that runs tracking:deliver-pending.',
        ];
        $latestReport = TrackingAiReport::query()->latest('created_at')->first();
        $reports = TrackingAiReport::query()->latest('created_at')->limit(20)->get();

        return view('admin.tracking.show', compact(
            'section', 'period', 'counts', 'comparisonCounts', 'metrics', 'deliveries', 'recentEvents', 'topLinks', 'topSearches',
            'destinations', 'definitions', 'routeRows', 'consentCounts', 'health', 'latestReport', 'reports', 'topProducts', 'dailySeries', 'sourceCounts', 'observationCounts', 'linkMetrics', 'dailyLinkSeries',
        ));
    }

    public function saveConfiguration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'destinations' => ['required', 'array'],
            'destinations.*.enabled' => ['required', 'boolean'],
            'destinations.*.browser_enabled' => ['required', 'boolean'],
            'destinations.*.server_enabled' => ['required', 'boolean'],
            'destinations.*.pixel_id' => ['nullable', 'string', 'max:100'],
            'destinations.*.measurement_id' => ['nullable', 'string', 'max:32'],
            'destinations.*.api_version' => ['nullable', 'string', 'max:16'],
            'destinations.*.region' => ['nullable', 'in:global,eu'],
            'destinations.*.test_event_code' => ['nullable', 'string', 'max:100'],
            'destinations.*.model' => ['nullable', 'string', 'max:100'],
            'destinations.*.language' => ['nullable', 'in:bn,en'],
            'destinations.*.monthly_token_budget' => ['nullable', 'integer', 'min:1000', 'max:10000000'],
            'destinations.*.daily_report_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'destinations.*.secrets.access_token' => ['nullable', 'string', 'max:4096'],
            'destinations.*.secrets.api_secret' => ['nullable', 'string', 'max:4096'],
            'destinations.*.secrets.api_key' => ['nullable', 'string', 'max:4096'],
            'destinations.*.revoke_secret' => ['nullable', 'boolean'],
        ]);

        $allowedCodes = ['meta', 'tiktok', 'ga4', 'openai'];
        try {
        DB::transaction(function () use ($data, $request, $allowedCodes) {
            foreach ($allowedCodes as $code) {
                if (! isset($data['destinations'][$code])) continue;
                $fields = $data['destinations'][$code];
                $destination = TrackingDestination::query()->where('code', $code)->firstOrFail();
                $settings = $destination->settings ?? [];
                foreach (['pixel_id', 'measurement_id', 'api_version', 'region', 'test_event_code', 'model', 'language', 'monthly_token_budget', 'daily_report_limit'] as $setting) {
                    if (array_key_exists($setting, $fields)) $settings[$setting] = trim((string) $fields[$setting]);
                }
                if ($code === 'meta' && ! empty($settings['api_version']) && ! preg_match('/^v\d+\.\d+$/', $settings['api_version'])) {
                    throw new RuntimeException('Meta API version must use the format vNN.N.');
                }
                if ($code === 'ga4' && ! empty($settings['measurement_id']) && ! preg_match('/^G-[A-Z0-9]+$/', $settings['measurement_id'])) {
                    throw new RuntimeException('GA4 Measurement ID should look like G-XXXXXXXXXX.');
                }
                $destination->update([
                    'enabled' => (bool) $fields['enabled'], 'browser_enabled' => (bool) $fields['browser_enabled'],
                    'server_enabled' => (bool) $fields['server_enabled'], 'settings' => $settings,
                ]);

                $credentials = $destination->credentials;
                $secrets = $credentials?->secrets ?? [];
                $secretKey = match ($code) { 'meta', 'tiktok' => 'access_token', 'ga4' => 'api_secret', 'openai' => 'api_key' };
                $submitted = trim((string) ($fields['secrets'][$secretKey] ?? ''));
                if ($submitted !== '') $secrets[$secretKey] = $submitted;
                if (! empty($fields['revoke_secret'])) unset($secrets[$secretKey]);
                if ($secrets !== []) {
                    TrackingDestinationCredential::query()->updateOrCreate(
                        ['destination_id' => $destination->id],
                        ['secrets' => $secrets, 'updated_by' => $request->user()->id, 'updated_at' => now()],
                    );
                } elseif ($credentials) {
                    $credentials->delete();
                }

                if ($destination->server_enabled && $code !== 'openai' && empty($secrets[$secretKey])) {
                    throw new RuntimeException(ucfirst($code).' server mode needs its server credential before it can be enabled.');
                }
                if ($destination->browser_enabled && in_array($code, ['meta', 'tiktok'], true) && empty($settings['pixel_id'])) {
                    throw new RuntimeException(ucfirst($code).' browser mode needs a Pixel ID.');
                }
                if ($destination->browser_enabled && $code === 'ga4' && empty($settings['measurement_id'])) {
                    throw new RuntimeException('GA4 browser mode needs a Measurement ID.');
                }
                if ($code === 'openai' && $destination->enabled && empty($secrets['api_key'])) {
                    throw new RuntimeException('OpenAI needs an API key before it can be enabled.');
                }

                TrackingAuditLog::query()->create([
                    'actor_id' => $request->user()->id, 'action' => 'configuration.updated', 'target' => $code,
                    'properties' => ['enabled' => $destination->enabled, 'browser_enabled' => $destination->browser_enabled, 'server_enabled' => $destination->server_enabled, 'credential_configured' => ! empty($secrets[$secretKey])],
                    'created_at' => now(),
                ]);
            }
        });
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Tracking configuration saved. Secret values stay encrypted and are never shown again.');
    }

    public function saveRouting(Request $request): RedirectResponse
    {
        $data = $request->validate(['routes' => ['nullable', 'array'], 'routes.*' => ['integer', 'exists:tracking_event_routes,id']]);
        $enabled = collect($data['routes'] ?? [])->map(fn ($id) => (int) $id)->all();
        DB::transaction(function () use ($enabled, $request) {
            TrackingEventRoute::query()->get()->each(fn ($route) => $route->update(['enabled' => in_array($route->id, $enabled, true)]));
            TrackingAuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'routing.updated', 'properties' => ['enabled_routes' => count($enabled)], 'created_at' => now()]);
        });
        return back()->with('success', 'Event routing saved. Existing events will not be replayed.');
    }

    public function saveEvents(Request $request): RedirectResponse
    {
        $data = $request->validate(['events' => ['nullable', 'array'], 'events.*' => ['integer', 'exists:tracking_event_definitions,id']]);
        $enabled = collect($data['events'] ?? [])->map(fn ($id) => (int) $id)->all();
        DB::transaction(function () use ($enabled, $request) {
            TrackingEventDefinition::query()->get()->each(fn ($event) => $event->update(['enabled' => in_array($event->id, $enabled, true)]));
            TrackingAuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'events.updated', 'properties' => ['enabled_events' => count($enabled)], 'created_at' => now()]);
        });
        return back()->with('success', 'Event definitions saved.');
    }

    public function testDestination(Request $request, TrackingDestination $destination, TestTrackingDestinationService $tests): RedirectResponse
    {
        try {
            $message = $tests->run($destination);
            TrackingAuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'destination.test', 'target' => $destination->code, 'properties' => ['result' => 'accepted'], 'created_at' => now()]);
            return back()->with('success', $message);
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'Provider request failed because of a network or transport error.';
            TrackingAuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'destination.test', 'target' => $destination->code, 'properties' => ['result' => 'failed', 'summary' => $message], 'created_at' => now()]);
            return back()->with('error', $message);
        }
    }

    public function retryDelivery(Request $request, TrackingDelivery $delivery): RedirectResponse
    {
        abort_unless(in_array($delivery->status, ['failed', 'pending'], true), 409, 'This delivery cannot be retried while it is processing.');
        abort_if(str_starts_with((string) $delivery->error_summary, 'Cancelled:'), 409, 'This delivery was cancelled because consent or event routing is no longer active.');
        $delivery->update(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(), 'error_summary' => null, 'http_status' => null, 'response_code' => null]);
        TrackingAuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'delivery.retry', 'target' => (string) $delivery->id, 'created_at' => now()]);
        if (config('queue.default') !== 'sync') DeliverTrackingEvent::dispatch($delivery->id)->onQueue('tracking');
        return back()->with('success', 'Delivery queued for retry.');
    }

    public function exportLinks(Request $request)
    {
        $period = $this->period($request);
        $query = TrackingEvent::query()->where('analytics_consent', true)->whereBetween('occurred_at', [$period['from']->utc(), $period['to']->utc()])
            ->whereIn('event_name', ['LinkClick', 'PhoneClick', 'EmailClick'])->whereNotNull('link_url')->orderBy('id');
        if (in_array($request->query('link_type'), ['internal', 'external', 'phone', 'email'], true)) $query->where('link_type', $request->query('link_type'));
        if (is_string($request->query('link_search')) && trim($request->query('link_search')) !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_substr(trim($request->query('link_search')), 0, 100)).'%';
            $query->where(fn ($builder) => $builder->where('link_url', 'like', $needle)->orWhere('link_host', 'like', $needle)->orWhere('link_label', 'like', $needle));
        }
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['event', 'destination', 'destination_type', 'source_page', 'label', 'occurred_at']);
            $query->chunk(500, function ($events) use ($out) {
                foreach ($events as $event) {
                    $row = [$event->event_name, $event->link_url, $event->link_type, $event->page_path, $event->link_label, $event->occurred_at?->toIso8601String()];
                    fputcsv($out, array_map(fn ($value) => $this->safeCsvCell((string) ($value ?? '')), $row));
                }
            });
            fclose($out);
        }, 'tracking-links.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/', $value) ? "'".$value : $value;
    }

    public function generateReport(Request $request, BusinessIntelligenceService $intelligence): RedirectResponse
    {
        $data = $request->validate(['language' => ['required', 'in:bn,en'], 'days' => ['required', 'integer', 'in:7,30,90']]);
        try {
            $report = $intelligence->generate((int) $request->user()->id, $data['language'], (int) $data['days']);
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'OpenAI report request failed because of a network or transport error.';
            return back()->with('error', $message);
        }
        TrackingAuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'ai.report.generated', 'target' => (string) $report->id, 'properties' => ['status' => $report->status, 'days' => $data['days'], 'language' => $data['language']], 'created_at' => now()]);
        return back()->with('success', $report->status === 'data_gap' ? 'There is not enough consented data yet. A data-gap report is ready.' : 'AI report generated.');
    }

    public function downloadReport(TrackingAiReport $report)
    {
        $payload = json_encode(['period' => [$report->period_from->toDateString(), $report->period_to->toDateString()], 'language' => $report->language, 'model' => $report->model, 'created_at' => $report->created_at?->toIso8601String(), 'report' => $report->report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return response()->streamDownload(fn () => print($payload), 'tracking-report-'.$report->id.'.json', ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    private function period(Request $request): array
    {
        $allowed = ['today' => 1, 'yesterday' => 1, '7' => 7, '30' => 30, '90' => 90];
        $key = (string) $request->query('period', '30');
        if ($key === 'custom') {
            $custom = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
            $from = CarbonImmutable::parse($custom['from'], config('app.timezone'))->startOfDay();
            $to = CarbonImmutable::parse($custom['to'], config('app.timezone'))->endOfDay();
            abort_if($from->diffInDays($to) > 366, 422, 'Custom analytics ranges are limited to 366 days.');
        } elseif ($key === 'yesterday') {
            $from = CarbonImmutable::yesterday()->startOfDay(); $to = CarbonImmutable::yesterday()->endOfDay();
        } else {
            $days = $allowed[$key] ?? 30;
            $from = $days === 1 ? CarbonImmutable::today()->startOfDay() : CarbonImmutable::today()->subDays($days - 1)->startOfDay();
            $to = CarbonImmutable::now()->endOfDay();
        }
        return ['key' => $key, 'from' => $from, 'to' => $to];
    }
}
