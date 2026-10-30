<?php

namespace App\Services\Tracking\Adapters;

use App\Models\TrackingDelivery;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Ga4MeasurementProtocolAdapter implements TrackingAdapter
{
    private const EVENTS = [
        'PageView' => 'page_view', 'ViewContent' => 'view_item', 'AddToCart' => 'add_to_cart',
        'InitiateCheckout' => 'begin_checkout', 'Purchase' => 'purchase', 'Search' => 'search',
        'LinkClick' => 'link_click', 'PhoneClick' => 'phone_click', 'EmailClick' => 'email_click',
    ];

    public function send(TrackingDelivery $delivery): array
    {
        $destination = $delivery->destination;
        $secrets = $destination->credentials?->secrets ?? [];
        $measurementId = (string) ($destination->settings['measurement_id'] ?? '');
        $apiSecret = (string) ($secrets['api_secret'] ?? '');
        $clientId = (string) ($delivery->event->private_context['ga_client_id'] ?? '');
        if (! $measurementId || ! $apiSecret || ! $clientId) {
            throw new RuntimeException('GA4 needs a measurement ID, API secret, and consented browser client ID.');
        }

        $event = $delivery->event;
        $params = array_filter([
            'transaction_id' => $event->event_name === 'Purchase' ? (string) $event->order_id : null,
            'value' => $event->value ? (float) $event->value : null,
            'currency' => $event->currency,
            'items' => $event->product_id ? [[
                'item_id' => $event->product_id,
                'price' => $event->value ? (float) $event->value : null,
                'quantity' => (int) ($event->properties['quantity'] ?? 1),
            ]] : (isset($event->properties['items']) ? array_map(fn ($item) => array_filter([
                'item_id' => $item['item_id'] ?? null,
                'price' => $item['price'] ?? null,
                'quantity' => $item['quantity'] ?? null,
            ], fn ($value) => $value !== null), $event->properties['items']) : null),
            'search_term' => $event->properties['search_term'] ?? null,
            'link_url' => $event->link_url,
            'engagement_time_msec' => 1,
        ], fn ($value) => $value !== null);
        $payload = ['client_id' => $clientId, 'timestamp_micros' => (string) ($event->occurred_at->timestamp * 1000000), 'events' => [[
            'name' => self::EVENTS[$event->event_name] ?? strtolower($event->event_name),
            'params' => $params,
        ]]];
        $base = ($destination->settings['region'] ?? 'global') === 'eu' ? 'https://region1.google-analytics.com' : 'https://www.google-analytics.com';
        $response = Http::asJson()->connectTimeout(3)->timeout(8)->post($base.'/mp/collect?'.http_build_query([
            'measurement_id' => $measurementId, 'api_secret' => $apiSecret,
        ]), $payload);

        // GA4 returns 2xx for malformed or unprocessed payloads; this is transport acceptance only.
        return ['http_status' => $response->status(), 'response_code' => $response->successful() ? 'accepted_unverified' : 'provider_error'];
    }
}
