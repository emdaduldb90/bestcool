<?php

namespace App\Services\Tracking;

use App\Models\TrackingConsent;
use App\Models\TrackingDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrackingConsentService
{
    public const COOKIE = 'bce_tracking_visitor';
    public const POLICY_VERSION = 1;

    public function visitorToken(Request $request, bool $create = false): ?string
    {
        $token = $request->cookie(self::COOKIE);

        if (is_string($token) && preg_match('/^[A-Za-z0-9-]{36,80}$/', $token)) {
            return $token;
        }

        return $create ? Str::random(64) : null;
    }

    public function visitorHash(?string $token): ?string
    {
        return $token ? hash_hmac('sha256', $token, (string) config('app.key')) : null;
    }

    public function consent(Request $request): ?TrackingConsent
    {
        $hash = $this->visitorHash($this->visitorToken($request));
        if (! $hash) {
            return null;
        }

        $consent = TrackingConsent::query()->where('visitor_hash', $hash)->first();

        return $consent && (! $consent->expires_at || $consent->expires_at->isFuture()) ? $consent : null;
    }

    public function remember(string $token, bool $analytics, bool $advertising, ?string $sessionId = null): TrackingConsent
    {
        $visitorHash = $this->visitorHash($token);
        $previous = TrackingConsent::query()->where('visitor_hash', $visitorHash)->first();
        $revokedCategories = [];
        if ($previous?->analytics && ! $analytics) $revokedCategories[] = 'analytics';
        if ($previous?->advertising && ! $advertising) $revokedCategories[] = 'advertising';

        return DB::transaction(function () use ($visitorHash, $analytics, $advertising, $sessionId, $revokedCategories) {
            $consent = TrackingConsent::query()->updateOrCreate(
                ['visitor_hash' => $visitorHash],
                [
                    'analytics' => $analytics,
                    'advertising' => $advertising,
                    'policy_version' => self::POLICY_VERSION,
                    'chosen_at' => now(),
                    'expires_at' => now()->addMonths(6),
                ],
            );

            if ($sessionId) {
                $sessionHash = hash_hmac('sha256', $sessionId, (string) config('app.key'));
                if ($analytics || $advertising) {
                    DB::table('tracking_sessions')->insertOrIgnore([
                        'session_hash' => $sessionHash, 'first_seen_at' => now(), 'last_seen_at' => now(),
                        'analytics_consent' => $analytics, 'advertising_consent' => $advertising, 'created_at' => now(),
                    ]);
                    DB::table('tracking_sessions')->where('session_hash', $sessionHash)->update([
                        'last_seen_at' => now(), 'analytics_consent' => $analytics, 'advertising_consent' => $advertising,
                    ]);
                } else {
                    DB::table('tracking_sessions')->where('session_hash', $sessionHash)->update([
                        'last_seen_at' => now(), 'analytics_consent' => false, 'advertising_consent' => false,
                    ]);
                }
            }

            if ($revokedCategories !== [] && $sessionId) {
                $sessionHash = hash_hmac('sha256', $sessionId, (string) config('app.key'));
                $deliveries = DB::table('tracking_deliveries as deliveries')
                    ->join('tracking_events as events', 'events.id', '=', 'deliveries.tracking_event_id')
                    ->join('tracking_event_definitions as definitions', 'definitions.name', '=', 'events.event_name')
                    ->join('tracking_event_routes as routes', function ($join) {
                        $join->on('routes.event_definition_id', '=', 'definitions.id')
                            ->on('routes.destination_id', '=', 'deliveries.destination_id');
                    })
                    ->where('routes.channel', 'server')
                    ->whereIn('routes.consent_category', $revokedCategories)
                    ->where('events.session_hash', $sessionHash)
                    ->where('deliveries.status', 'pending')
                    ->select('deliveries.id');

                TrackingDelivery::query()->whereIn('id', $deliveries)
                    ->update([
                        'status' => 'failed', 'next_attempt_at' => null,
                        'error_summary' => 'Cancelled: consent preferences changed.',
                    ]);
            }

            return $consent;
        });
    }
}
