<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPage extends Model
{
    protected $table = 'pages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'slug', 'title', 'meta_title', 'meta_description', 'og_image', 'created_at', 'updated_at'];

    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class, 'page_id')->orderBy('sort_order');
    }
}
