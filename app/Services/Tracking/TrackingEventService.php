<?php

namespace App\Services\Tracking;

use App\Jobs\DeliverTrackingEvent;
use App\Models\TrackingDelivery;
use App\Models\TrackingEvent;
use App\Models\TrackingEventDefinition;
use App\Models\TrackingEventObservation;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrackingEventService
{
    public const EVENTS = [
        'PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout', 'Purchase',
        'LinkClick', 'PhoneClick', 'EmailClick', 'Search',
    ];

    public function __construct(private readonly TrackingConsentService $consents) {}

    public function captureBrowser(Request $request, string $name, string $eventId, array $input = []): ?TrackingEvent
    {
        if ($request->user()?->isAdmin()) return null;
        try {
            $consent = $this->consents->consent($request);
            if (! $consent || (! $consent->analytics && ! $consent->advertising)) {
                return null;
            }
            $browserProperties = $this->normalizeProperties($name, $input);
            if (array_key_exists('page_path', $input) && ($browserProperties['page_path'] ?? null) === null && $name !== 'InitiateCheckout') {
                return null;
            }

            $existing = TrackingEvent::query()->where('event_id', $eventId)->first();
            if ($existing) {
                if ($existing->event_name !== $name) {
                    return null;
                }
                $this->observe($existing, 'browser', $input);
                return $existing;
            }

            // Cart, checkout and purchase facts must originate from successful Laravel actions.
            if (in_array($name, ['AddToCart', 'InitiateCheckout', 'Purchase'], true)) {
                return null;
            }

            return $this->store($request, $name, $eventId, $input, 'browser', $consent->analytics, $consent->advertising);
        } catch (Throwable $exception) {
            Log::warning('Browser tracking event capture failed.', ['event' => $name, 'exception' => $exception::class]);
            return null;
        }
    }

    public function captureTrusted(Request $request, string $name, array $input = [], ?string $eventId = null): ?TrackingEvent
    {
        if ($request->user()?->isAdmin()) return null;
        try {
            $consent = $this->consents->consent($request);
            if (! $consent || (! $consent->analytics && ! $consent->advertising)) return null;

            $eventId ??= (string) Str::uuid();
            $existing = TrackingEvent::query()->where('event_id', $eventId)->first();
            if ($existing) {
                if ($existing->event_name === $name) {
                    $this->observe($existing, 'server', $input);
                    return $existing;
                }
                return null;
            }

            if ($name === 'Purchase' && ! empty($input['order_id'])) {
                $prior = TrackingEvent::query()->where('order_id', $input['order_id'])->where('event_name', 'Purchase')->first();
                if ($prior) {
                    $this->observe($prior, 'server', $input);
                    return $prior;
                }
            }

            return $this->store($request, $name, $eventId, $input, 'server', $consent->analytics, $consent->advertising);
        } catch (Throwable $exception) {
            Log::warning('Trusted tracking event capture failed.', ['event' => $name, 'exception' => $exception::class]);
            return null;
        }
    }

    private function store(Request $request, string $name, string $eventId, array $input, string $source, bool $analytics, bool $advertising): ?TrackingEvent
    {
        $definition = TrackingEventDefinition::query()->where('name', $name)->where('enabled', true)->first();
        if (! $definition) {
            return null;
        }

        $clean = $this->normalizeProperties($name, $input);
        $link = $this->normalizeLink($name, $clean['link_url'] ?? null);
        $sessionId = ($analytics || $advertising) ? $request->session()->getId() : null;
        $created = true;

        try {
            $event = TrackingEvent::query()->create([
                'event_id' => $eventId,
                'event_name' => $name,
                'source' => $source,
                'occurred_at' => now()->utc(),
                'session_hash' => $sessionId ? hash_hmac('sha256', $sessionId, (string) config('app.key')) : null,
                'page_path' => array_key_exists('page_path', $clean) ? $clean['page_path'] : $this->safePath($request->path()),
                'referrer_host' => $clean['referrer_host'] ?? $this->safeHost($request->headers->get('referer')),
                'product_id' => $clean['product_id'] ?? null,
                'product_sku' => $clean['product_sku'] ?? null,
                'order_id' => $name === 'Purchase' ? ($clean['order_id'] ?? null) : null,
                'value' => $clean['value'] ?? null,
                'currency' => $clean['currency'] ?? null,
                'private_context' => ($analytics && ($clientId = $this->gaClientId($request))) ? ['ga_client_id' => $clientId] : null,
                'link_type' => $link['type'] ?? null,
                'link_url' => $link['url'] ?? null,
                'link_host' => $link['host'] ?? null,
                'link_label' => $clean['link_label'] ?? null,
                'analytics_consent' => $analytics,
                'advertising_consent' => $advertising,
                'schema_version' => 1,
                'properties' => $clean['properties'] ?? [],
            ]);
        } catch (QueryException $exception) {
            $event = TrackingEvent::query()->where('event_id', $eventId)->first();
            if (! $event || $event->event_name !== $name) {
                throw $exception;
            }
            $created = false;
        }

        try {
            $this->observe($event, $source, $input);
            if ($created && $event->analytics_consent) $this->aggregate($event);
            $this->route($event);
        } catch (Throwable $exception) {
            Log::warning('Tracking event post-processing failed.', ['event' => $name, 'exception' => $exception::class]);
        }

        return $event;
    }

    private function observe(TrackingEvent $event, string $source, array $input): void
    {
        TrackingEventObservation::query()->firstOrCreate(
            ['event_id' => $event->event_id, 'source' => $source],
            ['observed_at' => now(), 'properties' => array_intersect_key($this->normalizeProperties($event->event_name, $input), array_flip(['quantity', 'product_id']))],
        );
        if ($event->page_path === null && array_key_exists('page_path', $this->normalizeProperties($event->event_name, $input))) {
            $path = $this->normalizeProperties($event->event_name, $input)['page_path'];
            if ($path !== null) $event->forceFill(['page_path' => $path])->save();
        }
    }

    private function route(TrackingEvent $event): void
    {
        $definition = TrackingEventDefinition::query()->where('name', $event->event_name)->with(['routes.destination'])->first();
        foreach ($definition?->routes ?? [] as $route) {
            $destination = $route->destination;
            $hasConsent = $route->consent_category === 'advertising' ? $event->advertising_consent : $event->analytics_consent;
            if (! $route->enabled || ! $destination?->enabled || ! $destination->server_enabled || $route->channel !== 'server' || ! $hasConsent) {
                continue;
            }

            $delivery = TrackingDelivery::query()->firstOrCreate(
                ['tracking_event_id' => $event->id, 'destination_id' => $destination->id, 'channel' => 'server'],
                ['status' => 'pending', 'next_attempt_at' => now()],
            );

            if ($delivery->wasRecentlyCreated && config('queue.default') !== 'sync') {
                DeliverTrackingEvent::dispatch($delivery->id)->onQueue('tracking');
            }
        }
    }

    private function normalizeProperties(string $name, array $input): array
    {
        $result = [];
        foreach (['product_id' => 64, 'product_sku' => 100] as $key => $max) {
            if (isset($input[$key]) && is_scalar($input[$key])) {
                $result[$key] = Str::limit(trim(strip_tags((string) $input[$key])), $max, '');
            }
        }

        if (isset($input['quantity']) && is_numeric($input['quantity'])) {
            $result['quantity'] = max(1, min(99, (int) $input['quantity']));
        }
        if (isset($input['value']) && is_numeric($input['value']) && (float) $input['value'] >= 0) {
            $result['value'] = number_format(min((float) $input['value'], 999999999), 2, '.', '');
        }
        if (isset($input['currency']) && is_string($input['currency']) && preg_match('/^[A-Z]{3}$/', strtoupper($input['currency']))) {
            $result['currency'] = strtoupper($input['currency']);
        }
        if ($name === 'Purchase' && isset($input['order_id']) && is_numeric($input['order_id'])) {
            $result['order_id'] = (int) $input['order_id'];
        }
        if ($name === 'Search' && isset($input['search_term']) && is_string($input['search_term'])) {
            $term = trim(preg_replace('/\s+/u', ' ', strip_tags($input['search_term'])) ?? '');
            $term = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu', '[redacted]', $term) ?? '';
            $term = preg_replace('/(?<!\d)(?:\+?\d[\d\s().-]{6,}\d)(?!\d)/', '[redacted]', $term) ?? '';
            $result['properties']['search_term'] = Str::limit($term, 80, '');
            if (isset($input['result_count']) && is_numeric($input['result_count'])) {
                $result['properties']['result_count'] = max(0, min(100000, (int) $input['result_count']));
            }
        }
        if ($name === 'Purchase') {
            $result['properties']['order_status'] = 'PENDING';
            $result['properties']['payment_method'] = 'COD';
            $result['properties']['purchase_milestone'] = 'order_placed';
            if (isset($input['items']) && is_array($input['items'])) {
                $result['properties']['items'] = collect($input['items'])->take(20)->map(function ($item) {
                    if (! is_array($item)) return null;
                    return array_filter([
                        'item_id' => isset($item['item_id']) ? Str::limit(strip_tags((string) $item['item_id']), 64, '') : null,
                        'quantity' => isset($item['quantity']) && is_numeric($item['quantity']) ? max(1, min(99, (int) $item['quantity'])) : null,
                        'price' => isset($item['price']) && is_numeric($item['price']) ? max(0, min(999999999, (float) $item['price'])) : null,
                    ], fn ($value) => $value !== null);
                })->filter()->values()->all();
            }
        }

        if (in_array($name, ['LinkClick', 'PhoneClick', 'EmailClick'], true)) {
            $label = isset($input['link_label']) ? trim(preg_replace('/\s+/u', ' ', strip_tags((string) $input['link_label'])) ?? '') : '';
            $label = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu', '[redacted]', $label) ?? '';
            $label = preg_replace('/(?<!\d)\+?\d[\d\s().-]{6,}\d(?!\d)/', '[redacted]', $label) ?? '';
            $result['link_label'] = Str::limit($label, 120, '');
            $result['link_url'] = isset($input['link_url']) ? Str::limit((string) $input['link_url'], 1000, '') : null;
        }
        if (isset($input['page_path']) && is_string($input['page_path'])) {
            $path = parse_url($input['page_path'], PHP_URL_PATH);
            $result['page_path'] = is_string($path) && str_starts_with($path, '/') ? $this->safePath($path) : null;
        }
        if (isset($input['referrer_url']) && is_string($input['referrer_url'])) {
            $result['referrer_host'] = $this->safeHost($input['referrer_url']);
        }
        return $result;
    }

    private function normalizeLink(string $name, ?string $raw): array
    {
        if (! $raw || ! in_array($name, ['LinkClick', 'PhoneClick', 'EmailClick'], true)) return [];
        if (str_starts_with($raw, 'tel:') && $name === 'PhoneClick') return ['type' => 'phone', 'url' => 'tel:[redacted]'];
        if (str_starts_with($raw, 'mailto:') && $name === 'EmailClick') return ['type' => 'email', 'url' => 'mailto:[redacted]'];
        $parts = parse_url($raw);
        if (! is_array($parts) || empty($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) return [];
        $host = strtolower($parts['host']);
        $path = preg_replace('#/{2,}#', '/', $parts['path'] ?? '/') ?: '/';
        $lowerPath = strtolower($path);
        if (preg_match('#/(admin|login|logout|register|password|reset|checkout|order-success)(/|$)#', $lowerPath) || preg_match('/(token|secret|key|email|phone|password)/i', $lowerPath)) return [];
        $url = $host.($parts['port'] ?? null ? ':'.$parts['port'] : '').$path;
        $type = $host === strtolower((string) request()->getHost()) ? 'internal' : 'external';
        return ['type' => $type, 'url' => Str::limit($url, 512, ''), 'host' => Str::limit($host, 190, '')];
    }

    private function safePath(string $path): ?string
    {
        $path = '/'.ltrim($path, '/');
        if (preg_match('#^/(admin|account|cart|checkout|order-success|password|reset)(/|$)#i', $path)) return null;
        if (preg_match('/[?&](token|email|phone|password)=/i', $path)) return null;
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', $path) || preg_match('#/(?:[A-F0-9]{32,}|[A-Z0-9_-]{40,})(?:/|$)#i', $path) || preg_match('/\d{7,}/', $path)) return null;
        return Str::limit($path, 512, '');
    }

    private function safeHost(?string $url): ?string
    {
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;
        return is_string($host) ? Str::limit(strtolower($host), 190, '') : null;
    }

    private function aggregate(TrackingEvent $event): void
    {
        $date = $event->occurred_at->timezone(config('app.timezone'))->toDateString();
        $now = now();
        DB::table('tracking_daily_aggregates')->insertOrIgnore([
            'aggregate_date' => $date, 'event_name' => $event->event_name, 'source' => $event->source,
            'event_count' => 0, 'unique_sessions' => 0, 'order_value' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('tracking_daily_aggregates')->where('aggregate_date', $date)->where('event_name', $event->event_name)
            ->where('source', $event->source)->increment('event_count', 1, ['updated_at' => $now]);
        if ($event->event_name === 'Purchase' && $event->value !== null) {
            DB::table('tracking_daily_aggregates')->where('aggregate_date', $date)->where('event_name', 'Purchase')
                ->where('source', $event->source)->increment('order_value', (float) $event->value, ['updated_at' => $now]);
        }

        if ($event->session_hash) {
            DB::table('tracking_sessions')->insertOrIgnore([
                'session_hash' => $event->session_hash, 'first_seen_at' => $event->occurred_at,
                'last_seen_at' => $event->occurred_at, 'analytics_consent' => true,
                'advertising_consent' => $event->advertising_consent, 'created_at' => $now,
            ]);
            DB::table('tracking_sessions')->where('session_hash', $event->session_hash)->update([
                'last_seen_at' => $event->occurred_at, 'analytics_consent' => true,
                'advertising_consent' => $event->advertising_consent,
            ]);
        }

        if ($event->link_url && $event->link_type) {
            $linkHash = hash('sha256', implode('|', [$event->link_type, $event->link_url, $event->page_path ?? '']));
            DB::table('tracking_link_aggregates')->insertOrIgnore([
                'aggregate_date' => $date, 'link_hash' => $linkHash, 'link_type' => $event->link_type,
                'link_url' => $event->link_url, 'page_path' => $event->page_path, 'link_label' => $event->link_label,
                'click_count' => 0, 'unique_sessions' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('tracking_link_aggregates')->where('aggregate_date', $date)->where('link_hash', $linkHash)
                ->increment('click_count', 1, ['updated_at' => $now]);
        }
    }

    private function gaClientId(Request $request): ?string
    {
        $cookie = $request->cookie('_ga');
        if (! is_string($cookie) || ! preg_match('/^GA\d+\.\d+\.(\d+\.\d+)$/', $cookie, $matches)) {
            return null;
        }
        return $matches[1];
    }
}
