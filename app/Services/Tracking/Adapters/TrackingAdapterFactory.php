<?php

namespace App\Services\Tracking\Adapters;

use InvalidArgumentException;

class TrackingAdapterFactory
{
    public function make(string $code): TrackingAdapter
    {
        return match ($code) {
            'meta' => app(MetaConversionsAdapter::class),
            'tiktok' => app(TikTokEventsAdapter::class),
            'ga4' => app(Ga4MeasurementProtocolAdapter::class),
            default => throw new InvalidArgumentException('Unknown tracking destination.'),
        };
    }
}
