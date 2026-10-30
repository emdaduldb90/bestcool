<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_id', 'action', 'target', 'properties', 'created_at'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }
}
