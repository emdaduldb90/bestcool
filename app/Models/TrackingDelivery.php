<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingDelivery extends Model
{
    protected $fillable = ['tracking_event_id', 'destination_id', 'channel', 'status', 'attempts', 'next_attempt_at', 'last_attempt_at', 'http_status', 'response_code', 'error_summary', 'provider_event_id'];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'datetime', 'last_attempt_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(TrackingEvent::class, 'tracking_event_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(TrackingDestination::class, 'destination_id');
    }
}
