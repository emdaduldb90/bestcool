<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $hidden = ['session_hash', 'private_context'];

    protected $fillable = ['event_id', 'event_name', 'source', 'occurred_at', 'session_hash', 'page_path', 'referrer_host', 'product_id', 'product_sku', 'order_id', 'value', 'currency', 'private_context', 'link_type', 'link_url', 'link_host', 'link_label', 'analytics_consent', 'advertising_consent', 'schema_version', 'properties'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'analytics_consent' => 'boolean', 'advertising_consent' => 'boolean', 'properties' => 'array', 'private_context' => 'encrypted:array', 'value' => 'decimal:2'];
    }

    public function observations(): HasMany
    {
        return $this->hasMany(TrackingEventObservation::class, 'event_id', 'event_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(TrackingDelivery::class, 'tracking_event_id');
    }
}
