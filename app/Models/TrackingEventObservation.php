<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingEventObservation extends Model
{
    public $timestamps = false;

    protected $fillable = ['event_id', 'source', 'observed_at', 'properties'];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime', 'properties' => 'array'];
    }
}
