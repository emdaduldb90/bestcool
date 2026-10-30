<?php

namespace App\Services\Tracking\Adapters;

use App\Models\TrackingDelivery;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaConversionsAdapter implements TrackingAdapter
{
    public function send(TrackingDelivery $delivery): array
    {
        $destination = $delivery->destination;
        $secrets = $destination->credentials?->secrets ?? [];
        $pixelId = (string) ($destination->settings['pixel_id'] ?? '');
        $token = (string) ($secrets['access_token'] ?? '');
        $version = (string) ($destination->settings['api_version'] ?? '');
        if (! $pixelId || ! $token || ! preg_match('/^v\d+\.\d+$/', $version)) {
            throw new RuntimeException('Meta server credentials or API version are not configured.');
        }

        $event = $delivery->event;
        $eventName = in_array($event->event_name, ['PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout', 'Purchase', 'Search'], true)
            ? $event->event_name
            : $event->event_name;
        $contentIds = collect($event->properties['items'] ?? [])->pluck('item_id')->filter()->values()->all();
        if ($contentIds === [] && $event->product_id) $contentIds = [$event->product_id];
        $payload = [
            'data' => [[
                'event_name' => $eventName,
                'event_time' => $event->occurred_at->timestamp,
                'event_id' => $event->event_id,
                'action_source' => 'website',
                'event_source_url' => url($event->page_path ?: '/'),
                'custom_data' => array_filter([
                    'value' => $event->value ? (float) $event->value : null,
                    'currency' => $event->currency,
                    'content_ids' => $contentIds !== [] ? $contentIds : null,
                    'content_type' => $contentIds !== [] ? 'product' : null,
                    'order_id' => $event->event_name === 'Purchase' ? (string) $event->order_id : null,
                ], fn ($value) => $value !== null),
            ]],
        ];
        if (! empty($destination->settings['test_event_code'])) {
            $payload['test_event_code'] = $destination->settings['test_event_code'];
        }

        $response = Http::asJson()->withToken($token)->connectTimeout(3)->timeout(8)
            ->post("https://graph.facebook.com/{$version}/{$pixelId}/events", $payload);
        return ['http_status' => $response->status(), 'response_code' => $response->successful() ? 'accepted' : 'provider_error'];
    }
}
