<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingAiReport extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['generated_by', 'period_from', 'period_to', 'language', 'model', 'status', 'input_tokens', 'output_tokens', 'input_fingerprint', 'report'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'report' => 'array'];
    }
}
