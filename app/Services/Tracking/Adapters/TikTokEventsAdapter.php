<?php

namespace App\Services\Tracking\Adapters;

use App\Models\TrackingDelivery;
use Illuminate\Support\Facades\Http;

class TikTokEventsAdapter implements TrackingAdapter
{
    public function send(TrackingDelivery $delivery): array
    {
        $destination = $delivery->destination;
        $secrets = $destination->credentials?->secrets ?? [];
        $pixelId = (string) ($destination->settings['pixel_id'] ?? '');
        $token = (string) ($secrets['access_token'] ?? '');
        abort_if(! $pixelId || ! $token, 422, 'TikTok server credentials are not configured.');

        $event = $delivery->event;
        $eventName = $event->event_name === 'PageView' ? 'Pageview' : $event->event_name;
        $item = [
            'event' => $eventName,
            'event_time' => $event->occurred_at->timestamp,
            'event_id' => $event->event_id,
            'page' => ['url' => url($event->page_path ?: '/')],
            'properties' => array_filter([
                'value' => $event->value ? (float) $event->value : null,
                'currency' => $event->currency,
                'content_id' => $event->product_id,
                'quantity' => $event->properties['quantity'] ?? null,
                'contents' => $event->properties['items'] ?? null,
            ], fn ($value) => $value !== null),
        ];
        $payload = ['event_source' => 'web', 'event_source_id' => $pixelId, 'data' => [$item]];
        if (! empty($destination->settings['test_event_code'])) {
            $payload['test_event_code'] = $destination->settings['test_event_code'];
        }

        $response = Http::asJson()->withHeaders(['Access-Token' => $token])->connectTimeout(3)->timeout(8)
            ->post('https://business-api.tiktok.com/open_api/v1.3/event/track/', $payload);
        $body = $response->json();
        $accepted = $response->successful() && is_array($body) && (int) ($body['code'] ?? -1) === 0;
        return ['http_status' => $response->status(), 'response_code' => $accepted ? 'accepted' : (string) ($body['code'] ?? 'provider_error')];
    }
}
