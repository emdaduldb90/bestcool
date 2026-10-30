<?php

namespace App\Http\Controllers;

use App\Models\TrackingDestination;
use App\Models\TrackingEventDefinition;
use App\Services\Tracking\TrackingConsentService;
use App\Services\Tracking\TrackingEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class TrackingController extends Controller
{
    public function __construct(private readonly TrackingConsentService $consents, private readonly TrackingEventService $events) {}

    public function consent(Request $request): JsonResponse
    {
        $data = $request->validate(['analytics' => ['required', 'boolean'], 'advertising' => ['required', 'boolean']]);
        $token = $this->consents->visitorToken($request, true);
        $consent = $this->consents->remember($token, (bool) $data['analytics'], (bool) $data['advertising'], $request->session()->getId());

        return response()->json(['saved' => true, 'consent' => ['analytics' => $consent->analytics, 'advertising' => $consent->advertising]])
            ->withCookie(Cookie::make(TrackingConsentService::COOKIE, $token, 60 * 24 * 180, '/', null, $request->isSecure(), true, false, 'lax'));
    }

    public function config(Request $request): JsonResponse
    {
        $consent = $this->consents->consent($request);
        $result = ['consent' => $consent ? ['analytics' => $consent->analytics, 'advertising' => $consent->advertising] : null, 'destinations' => []];
        if (! $consent || $request->user()?->isAdmin()) return response()->json($result)->header('Cache-Control', 'private, no-store');

        $destinations = TrackingDestination::query()->where('enabled', true)->where('browser_enabled', true)->get()->keyBy('code');
        foreach (['meta' => 'advertising', 'tiktok' => 'advertising', 'ga4' => 'analytics'] as $code => $category) {
            if (! $consent->{$category} || ! $destinations->has($code)) continue;
            $destination = $destinations->get($code);
            $events = TrackingEventDefinition::query()->where('enabled', true)->whereHas('routes', fn ($query) => $query
                ->where('destination_id', $destination->id)->where('channel', 'browser')->where('enabled', true))
                ->pluck('name')->values()->all();
            if ($code === 'meta' && filled($destination->settings['pixel_id'] ?? null)) {
                $result['destinations']['meta'] = ['pixel_id' => (string) $destination->settings['pixel_id'], 'events' => $events];
            } elseif ($code === 'tiktok' && filled($destination->settings['pixel_id'] ?? null)) {
                $result['destinations']['tiktok'] = ['pixel_id' => (string) $destination->settings['pixel_id'], 'events' => $events];
            } elseif ($code === 'ga4' && filled($destination->settings['measurement_id'] ?? null)) {
                $result['destinations']['ga4'] = ['measurement_id' => (string) $destination->settings['measurement_id'], 'events' => $events];
            }
        }

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function event(Request $request): JsonResponse
    {
        abort_if((int) $request->header('Content-Length', 0) > 8192, 413);
        $data = $request->validate([
            'event_id' => ['required', 'uuid'],
            'event_name' => ['required', 'string', 'in:'.implode(',', TrackingEventService::EVENTS)],
            'properties' => ['nullable', 'array', 'max:12'],
            'properties.product_id' => ['nullable', 'string', 'max:64'],
            'properties.product_sku' => ['nullable', 'string', 'max:100'],
            'properties.quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'properties.value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'properties.currency' => ['nullable', 'string', 'size:3'],
            'properties.search_term' => ['nullable', 'string', 'max:200'],
            'properties.result_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'properties.link_url' => ['nullable', 'string', 'max:1000'],
            'properties.link_label' => ['nullable', 'string', 'max:190'],
            'properties.page_path' => ['nullable', 'string', 'max:512'],
            'properties.referrer_url' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->events->captureBrowser($request, $data['event_name'], $data['event_id'], $data['properties'] ?? []);

        return response()->json(['accepted' => true], 202)->header('Cache-Control', 'no-store');
    }
}
