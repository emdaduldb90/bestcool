<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingConsent extends Model
{
    protected $hidden = ['visitor_hash'];
    protected $fillable = ['visitor_hash', 'analytics', 'advertising', 'policy_version', 'chosen_at', 'expires_at'];

    protected function casts(): array
    {
        return ['analytics' => 'boolean', 'advertising' => 'boolean', 'chosen_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
