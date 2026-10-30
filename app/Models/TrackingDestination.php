<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrackingDestination extends Model
{
    protected $fillable = ['code', 'name', 'enabled', 'browser_enabled', 'server_enabled', 'settings'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'browser_enabled' => 'boolean', 'server_enabled' => 'boolean', 'settings' => 'array'];
    }

    public function credentials(): HasOne
    {
        return $this->hasOne(TrackingDestinationCredential::class, 'destination_id');
    }
}
