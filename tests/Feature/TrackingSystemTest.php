<?php

namespace Tests\Feature;

use App\Http\Controllers\TrackingController;
use App\Models\TrackingAiReport;
use App\Models\TrackingDelivery;
use App\Models\TrackingDestination;
use App\Models\TrackingDestinationCredential;
use App\Models\TrackingEvent;
use App\Models\TrackingEventObservation;
use App\Models\TrackingEventRoute;
use App\Models\TrackingEventDefinition;
use App\Models\User;
use App\Services\Tracking\BusinessIntelligenceService;
use App\Services\Tracking\TrackingConsentService;
use App\Services\Tracking\TrackingDeliveryProcessor;
use App\Services\Tracking\TrackingEventService;
use Database\Seeders\AdminAccessSeeder;
use Database\Seeders\TrackingSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrackingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminAccessSeeder::class);
        $this->seed(TrackingSystemSeeder::class);
    }

    public function test_tracking_panel_uses_the_existing_admin_login_and_denies_customers(): void
    {
        $this->get(route('admin.tracking.show'))->assertRedirect(route('login'));

        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.tracking.show'))->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true, 'is_active' => true])->save();
        $this->actingAs($admin)->get(route('admin.tracking.show'))->assertOk()->assertSee('Tracking')->assertSee('System health');
    }

    public function test_custom_analytics_range_is_bounded(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true, 'is_active' => true])->save();

        $this->actingAs($admin)->get(route('admin.tracking.show', [
            'period' => 'custom', 'from' => '2024-01-01', 'to' => '2026-01-01',
        ]))->assertStatus(422);
    }

    public function test_event_collection_requires_consent_and_deduplicates_browser_observation(): void
    {
        $service = app(TrackingEventService::class);
        $eventId = (string) Str::uuid();
        $request = $this->requestWithConsent(false, false);

        $this->assertNull($service->captureBrowser($request, 'PageView', $eventId, ['page_path' => '/products/sample']));
        $this->assertDatabaseCount('tracking_events', 0);

        $request = $this->requestWithConsent(true, false);
        $event = $service->captureTrusted($request, 'AddToCart', ['product_id' => 'P-1', 'quantity' => 1, 'value' => 500, 'currency' => 'BDT']);
        $this->assertNotNull($event);
        $service->captureBrowser($request, 'AddToCart', $event->event_id, [
            'product_id' => 'P-1', 'quantity' => 1, 'page_path' => '/product/sample',
        ]);

        $this->assertDatabaseCount('tracking_events', 1);
        $this->assertDatabaseCount('tracking_event_observations', 2);
        $this->assertDatabaseHas('tracking_daily_aggregates', ['event_name' => 'AddToCart', 'event_count' => 1]);
        $this->assertSame('/product/sample', $event->fresh()->page_path);
    }

    public function test_advertising_only_consent_does_not_enter_analytics_aggregates(): void
    {
        $request = $this->requestWithConsent(false, true);
        $event = app(TrackingEventService::class)->captureBrowser($request, 'PageView', (string) Str::uuid(), ['page_path' => '/']);

        $this->assertNotNull($event);
        $this->assertFalse($event->analytics_consent);
        $this->assertTrue($event->advertising_consent);
        $this->assertDatabaseCount('tracking_daily_aggregates', 0);
    }

    public function test_browser_cannot_forge_a_purchase_and_can_only_observe_a_server_purchase(): void
    {
        $request = $this->requestWithConsent(true, true);
        $service = app(TrackingEventService::class);

        $this->assertNull($service->captureBrowser($request, 'Purchase', (string) Str::uuid(), ['value' => 9000]));
        $eventId = (string) Str::uuid();
        $purchase = $service->captureTrusted($request, 'Purchase', ['value' => 9000, 'currency' => 'BDT'], $eventId);
        $replayed = $service->captureTrusted($request, 'Purchase', ['value' => 9000, 'currency' => 'BDT'], $eventId);

        $this->assertSame($purchase->id, $replayed->id);
        $this->assertDatabaseCount('tracking_events', 1);
        $this->assertDatabaseHas('tracking_daily_aggregates', ['event_name' => 'Purchase', 'event_count' => 1]);
        $this->assertDatabaseCount('tracking_event_observations', 1);
    }

    public function test_changed_consent_cancels_pending_delivery_for_the_current_session(): void
    {
        $request = $this->requestWithConsent(true, true);
        $token = $request->cookie(TrackingConsentService::COOKIE);
        $sessionHash = hash_hmac('sha256', $request->session()->getId(), (string) config('app.key'));
        $event = TrackingEvent::query()->create([
            'event_id' => (string) Str::uuid(), 'event_name' => 'PageView', 'source' => 'server', 'occurred_at' => now()->utc(),
            'session_hash' => $sessionHash, 'page_path' => '/', 'analytics_consent' => true, 'advertising_consent' => true,
            'schema_version' => 1, 'properties' => [],
        ]);
        $meta = TrackingDestination::query()->where('code', 'meta')->firstOrFail();
        $delivery = TrackingDelivery::query()->create([
            'tracking_event_id' => $event->id, 'destination_id' => $meta->id, 'channel' => 'server', 'status' => 'pending',
            'attempts' => 0, 'next_attempt_at' => now(),
        ]);

        app(TrackingConsentService::class)->remember($token, false, false, $request->session()->getId());

        $this->assertDatabaseHas('tracking_deliveries', [
            'id' => $delivery->id, 'status' => 'failed', 'next_attempt_at' => null,
            'error_summary' => 'Cancelled: consent preferences changed.',
        ]);
        $this->assertFalse(app(TrackingConsentService::class)->consent($request)?->analytics);
    }

    public function test_meta_delivery_uses_a_mock_and_retries_never_resend_a_delivered_event(): void
    {
        $meta = TrackingDestination::query()->where('code', 'meta')->firstOrFail();
        $meta->update(['enabled' => true, 'server_enabled' => true, 'settings' => ['pixel_id' => '123456789', 'api_version' => 'v20.0']]);
        $meta->credentials()->create(['secrets' => ['access_token' => 'mock-token'], 'updated_by' => null]);
        $definition = TrackingEventDefinition::query()->where('name', 'ViewContent')->firstOrFail();
        TrackingEventRoute::query()->where('event_definition_id', $definition->id)->where('destination_id', $meta->id)
            ->where('channel', 'server')->update(['enabled' => true]);
        $sessionHash = hash_hmac('sha256', 'provider-test-session', (string) config('app.key'));
        \Illuminate\Support\Facades\DB::table('tracking_sessions')->insert([
            'session_hash' => $sessionHash, 'first_seen_at' => now(), 'last_seen_at' => now(),
            'analytics_consent' => true, 'advertising_consent' => true, 'created_at' => now(),
        ]);
        $event = TrackingEvent::query()->create([
            'event_id' => (string) Str::uuid(), 'event_name' => 'ViewContent', 'source' => 'server', 'occurred_at' => now()->utc(),
            'session_hash' => $sessionHash, 'page_path' => '/products/test', 'product_id' => 'P-1', 'analytics_consent' => true, 'advertising_consent' => true,
            'schema_version' => 1, 'properties' => [],
        ]);
        $delivery = TrackingDelivery::query()->create([
            'tracking_event_id' => $event->id, 'destination_id' => $meta->id, 'channel' => 'server', 'status' => 'pending',
            'attempts' => 0, 'next_attempt_at' => now(),
        ]);
        Http::fake(['https://graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

        $processor = app(TrackingDeliveryProcessor::class);
        $this->assertTrue($processor->process($delivery->id)['processed']);
        $processor->process($delivery->id);

        $this->assertDatabaseHas('tracking_deliveries', ['id' => $delivery->id, 'status' => 'delivered', 'http_status' => 200]);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v20.0/123456789/events')
            && $request->hasHeader('Authorization', 'Bearer mock-token')
            && ($request['data'][0]['event_id'] ?? null) === $event->event_id);
    }

    public function test_encrypted_credentials_are_not_serialized_or_in_public_browser_config(): void
    {
        $meta = TrackingDestination::query()->where('code', 'meta')->firstOrFail();
        $meta->update(['enabled' => true, 'browser_enabled' => true, 'server_enabled' => true, 'settings' => ['pixel_id' => '1234567890', 'api_version' => 'v99.0']]);
        $meta->credentials()->create(['secrets' => ['access_token' => 'secret-token-for-test'], 'updated_by' => null]);
        $credential = TrackingDestinationCredential::query()->firstOrFail();

        $this->assertStringNotContainsString('secret-token-for-test', (string) $credential->getRawOriginal('secrets'));
        $this->assertArrayNotHasKey('secrets', $credential->toArray());

        $ga4 = TrackingDestination::query()->where('code', 'ga4')->firstOrFail();
        $ga4->update(['enabled' => true, 'browser_enabled' => true, 'server_enabled' => false, 'settings' => ['measurement_id' => 'G-TEST123']]);
        $token = Str::random(64);
        app(TrackingConsentService::class)->remember($token, true, false);
        $request = Request::create('/tracking/config', 'GET', [], [TrackingConsentService::COOKIE => $token]);
        $response = app(TrackingController::class)->config($request)->getData(true);
        $this->assertSame('G-TEST123', $response['destinations']['ga4']['measurement_id']);
        $this->assertArrayNotHasKey('api_secret', $response['destinations']['ga4']);
        $this->assertStringNotContainsString('secret-token-for-test', json_encode($response));
        $this->assertArrayNotHasKey('meta', $response['destinations']);
    }

    public function test_search_pii_is_redacted_and_short_history_produces_a_local_data_gap_report(): void
    {
        $request = $this->requestWithConsent(true, false);
        $event = app(TrackingEventService::class)->captureBrowser($request, 'Search', (string) Str::uuid(), [
            'search_term' => 'customer@example.com', 'result_count' => 2, 'page_path' => '/shop',
        ]);
        $this->assertSame('[redacted]', $event->properties['search_term']);

        $user = User::factory()->create();
        $report = app(BusinessIntelligenceService::class)->generate($user->id, 'bn', 30);
        $this->assertSame('data_gap', $report->status);
        $this->assertSame('local-data-gap', $report->model);
        $this->assertStringNotContainsString('customer@example.com', json_encode($report->report));
    }

    private function requestWithConsent(bool $analytics, bool $advertising): Request
    {
        $token = Str::random(64);
        app(TrackingConsentService::class)->remember($token, $analytics, $advertising);
        $request = Request::create('https://bestcool.test/cart/items/P-1', 'POST', [], [TrackingConsentService::COOKIE => $token]);
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        return $request;
    }
}
