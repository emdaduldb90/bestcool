<?php

namespace App\Support;

use App\Models\AdminActivity;
use Illuminate\Database\Eloquent\Model;

class AdminAudit
{
    public static function record(string $action, string $description, ?Model $subject = null, array $properties = []): void
    {
        AdminActivity::query()->create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey() === null ? null : (string) $subject->getKey(),
            'description' => $description,
            'properties' => self::redact($properties),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    private static function redact(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (preg_match('/password|secret|token|authorization|cookie|key/i', (string) $key)) {
                unset($properties[$key]);
            } elseif (is_array($value)) {
                $properties[$key] = self::redact($value);
            }
        }

        return $properties;
    }
}
