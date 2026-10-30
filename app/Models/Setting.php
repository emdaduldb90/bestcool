<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_at'];

    protected function casts(): array
    {
        return ['value' => 'json', 'updated_at' => 'datetime'];
    }
}
