<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEventRoute extends Model
{
    protected $fillable = ['event_definition_id', 'destination_id', 'channel', 'consent_category', 'enabled', 'priority', 'property_mapping'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'property_mapping' => 'array'];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(TrackingEventDefinition::class, 'event_definition_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(TrackingDestination::class, 'destination_id');
    }
}
