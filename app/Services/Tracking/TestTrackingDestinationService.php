<?php

namespace App\Services\Tracking;

use App\Models\TrackingDelivery;
use App\Models\TrackingDestination;
use App\Models\TrackingEvent;
use App\Services\Tracking\Adapters\TrackingAdapterFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class TestTrackingDestinationService
{
    public function run(TrackingDestination $destination): string
    {
        $destination->loadMissing('credentials');
        $settings = $destination->settings ?? [];
        $secrets = $destination->credentials?->secrets ?? [];

        if (in_array($destination->code, ['meta', 'tiktok'], true)) {
            if (empty($settings['test_event_code'])) throw new RuntimeException('Add a provider Test Events code before sending a synthetic test event.');
            $event = new TrackingEvent(['event_id' => (string) Str::uuid(), 'event_name' => 'PageView', 'occurred_at' => now(), 'page_path' => '/tracking-test', 'currency' => 'BDT']);
            $delivery = new TrackingDelivery(['channel' => 'server']);
            $delivery->setRelation('event', $event);
            $delivery->setRelation('destination', $destination);
            $result = app(\App\Services\Tracking\Adapters\TrackingAdapterFactory::class)->make($destination->code)->send($delivery);
            if ($result['http_status'] < 200 || $result['http_status'] >= 300 || $result['response_code'] !== 'accepted') {
                throw new RuntimeException('Provider did not accept the test event (HTTP '.$result['http_status'].').');
            }
            return 'Synthetic test event accepted by the API. Check the provider Test Events panel; this does not prove attribution.';
        }

        if ($destination->code === 'ga4') {
            $measurementId = (string) ($settings['measurement_id'] ?? '');
            $secret = (string) ($secrets['api_secret'] ?? '');
            if (! $measurementId || ! $secret) throw new RuntimeException('Configure the GA4 Measurement ID and API secret first.');
            $base = ($settings['region'] ?? 'global') === 'eu' ? 'https://region1.google-analytics.com' : 'https://www.google-analytics.com';
            $response = Http::asJson()->connectTimeout(3)->timeout(8)->post($base.'/debug/mp/collect?'.http_build_query([
                'measurement_id' => $measurementId, 'api_secret' => $secret,
            ]), ['client_id' => '555.123', 'validation_behavior' => 'ENFORCE_RECOMMENDATIONS', 'events' => [['name' => 'page_view', 'params' => ['page_location' => url('/tracking-test'), 'engagement_time_msec' => 1]]]]);
            if (! $response->successful()) throw new RuntimeException('GA4 validation endpoint returned HTTP '.$response->status().'.');
            $messages = $response->json('validationMessages', []);
            if (is_array($messages) && count($messages)) throw new RuntimeException('GA4 payload validation reported issues. Review the Measurement Protocol debugger.');
            return 'GA4 payload passed the debug endpoint. That endpoint does not verify the API secret or guarantee event processing.';
        }

        if ($destination->code === 'openai') {
            $apiKey = (string) ($secrets['api_key'] ?? '');
            if (! $apiKey) throw new RuntimeException('Configure an OpenAI API key first.');
            $response = Http::withToken($apiKey)->asJson()->connectTimeout(5)->timeout(30)->post('https://api.openai.com/v1/responses', [
                'model' => (string) ($settings['model'] ?? 'gpt-6-astra'), 'input' => 'Reply with the word OK.', 'max_output_tokens' => 8,
            ]);
            if (! $response->successful()) throw new RuntimeException('OpenAI returned HTTP '.$response->status().'. Check the configured key, model and account access.');
            return 'OpenAI returned a successful response. A small API request was made and may incur usage.';
        }

        throw new RuntimeException('This destination does not have a test adapter.');
    }
}
