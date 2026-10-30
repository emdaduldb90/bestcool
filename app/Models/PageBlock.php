<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageBlock extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'page_id', 'type', 'content', 'sort_order', 'is_enabled'];

    protected function casts(): array
    {
        return ['content' => 'array', 'is_enabled' => 'boolean'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(ContentPage::class, 'page_id');
    }
}
