<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'slug', 'brand', 'description', 'specifications', 'questions', 'sku',
        'regular_price', 'sale_price', 'stock', 'low_stock_threshold', 'is_active', 'sold_count',
        'category_id', 'created_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'regular_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getPriceAttribute(): string
    {
        return $this->sale_price && (float) $this->sale_price > 0
            ? $this->sale_price
            : $this->regular_price;
    }

    public function getPrimaryImageAttribute(): ?string
    {
        return $this->images->first()?->url;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
