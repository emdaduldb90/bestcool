<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingEventDefinition extends Model
{
    protected $fillable = ['name', 'label', 'enabled', 'consent_category', 'allowed_properties'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'allowed_properties' => 'array'];
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TrackingEventRoute::class, 'event_definition_id');
    }
}
