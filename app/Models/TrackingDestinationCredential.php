<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingDestinationCredential extends Model
{
    public $timestamps = false;

    protected $hidden = ['secrets', 'updated_by'];

    protected $fillable = ['destination_id', 'secrets', 'updated_by', 'updated_at'];

    protected function casts(): array
    {
        return ['secrets' => 'encrypted:array', 'updated_at' => 'datetime'];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(TrackingDestination::class, 'destination_id');
    }
}
