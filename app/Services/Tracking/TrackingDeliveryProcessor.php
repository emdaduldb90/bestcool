<?php

namespace App\Services\Tracking;

use App\Models\TrackingDelivery;
use App\Models\TrackingEventDefinition;
use App\Models\TrackingEventRoute;
use App\Services\Tracking\Adapters\TrackingAdapterFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class TrackingDeliveryProcessor
{
    /** @return array{processed:bool,retry_after?:int} */
    public function process(int $deliveryId): array
    {
        $claimed = TrackingDelivery::query()->whereKey($deliveryId)->where('status', 'pending')
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->update(['status' => 'processing', 'updated_at' => now()]);
        if (! $claimed) return ['processed' => false];

        $delivery = TrackingDelivery::query()->with(['event', 'destination.credentials'])->find($deliveryId);
        if (! $delivery || ! $delivery->event || ! $delivery->destination?->enabled || ! $delivery->destination?->server_enabled) {
            $delivery?->update(['status' => 'failed', 'error_summary' => 'Cancelled: destination is disabled or event is unavailable.', 'next_attempt_at' => null]);
            return ['processed' => true];
        }

        $definition = TrackingEventDefinition::query()->where('name', $delivery->event->event_name)->first();
        $route = $definition ? TrackingEventRoute::query()->where('event_definition_id', $definition->id)
            ->where('destination_id', $delivery->destination_id)->where('channel', 'server')->first() : null;
        $session = $delivery->event->session_hash
            ? DB::table('tracking_sessions')->where('session_hash', $delivery->event->session_hash)->first()
            : null;
        $consentGranted = $route?->consent_category === 'advertising'
            ? (bool) ($delivery->event->advertising_consent && $session?->advertising_consent)
            : (bool) ($route && $delivery->event->analytics_consent && $session?->analytics_consent);

        if (! $route?->enabled || ! $consentGranted) {
            $delivery->update(['status' => 'failed', 'error_summary' => 'Cancelled: consent or event routing is no longer active.', 'next_attempt_at' => null]);
            return ['processed' => true];
        }

        $key = 'tracking-destination:'.$delivery->destination_id;
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $delivery->update(['status' => 'pending', 'next_attempt_at' => now()->addMinute()]);
            return ['processed' => false, 'retry_after' => 60];
        }
        RateLimiter::hit($key, 60);

        $attempt = (int) $delivery->attempts + 1;
        $delivery->update(['attempts' => $attempt, 'last_attempt_at' => now()]);

        try {
            $result = app(TrackingAdapterFactory::class)->make($delivery->destination->code)->send($delivery);
            $accepted = $result['http_status'] >= 200 && $result['http_status'] < 300 && in_array($result['response_code'], ['accepted', 'accepted_unverified'], true);
            if ($accepted) {
                $delivery->update([
                    'status' => 'delivered',
                    'http_status' => $result['http_status'],
                    'response_code' => $result['response_code'],
                    'error_summary' => null,
                    'next_attempt_at' => null,
                ]);
                return ['processed' => true];
            }

            $status = (int) $result['http_status'];
            $retryable = $status >= 500 || $status === 429;
            return $this->finishFailure($delivery, $attempt, 'http_'.$status, $status, $retryable);
        } catch (Throwable $exception) {
            return $this->finishFailure($delivery, $attempt, $this->safeError($exception), null, true);
        }
    }

    private function finishFailure(TrackingDelivery $delivery, int $attempt, string $code, ?int $httpStatus, bool $retryable): array
    {
        $retryDelays = [15, 60, 240];
        $canRetry = $retryable && $attempt < count($retryDelays);
        $baseDelay = $retryDelays[min($attempt - 1, count($retryDelays) - 1)];
        $delay = $baseDelay + random_int(0, max(1, (int) floor($baseDelay / 5)));
        $delivery->update([
            'status' => $canRetry ? 'pending' : 'failed',
            'http_status' => $httpStatus,
            'response_code' => preg_match('/^[A-Za-z0-9_-]{1,80}$/', $code) ? $code : 'provider_error',
            'error_summary' => $code === 'provider_error' ? 'Provider request failed.' : 'Delivery failed: '.$code.'.',
            'next_attempt_at' => $canRetry ? now()->addSeconds($delay) : null,
        ]);
        return $canRetry ? ['processed' => true, 'retry_after' => $delay] : ['processed' => true];
    }

    private function safeError(Throwable $exception): string
    {
        $message = $exception->getMessage();
        if (str_contains($message, 'credentials') || str_contains($message, 'client ID')) {
            return preg_replace('/[^A-Za-z0-9 ._-]/', '', $message) ?: 'configuration_error';
        }
        return 'provider_error';
    }
}
