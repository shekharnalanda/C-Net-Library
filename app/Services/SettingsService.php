<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    public function get(string $key, mixed $default = null, ?int $branchId = null): mixed
    {
        $setting = Setting::query()
            ->where('key', $key)
            ->when($branchId, fn ($query) => $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->when(!$branchId, fn ($query) => $query->whereNull('branch_id'))
            ->orderByRaw('branch_id IS NULL')
            ->first();

        if (!$setting) {
            return $default;
        }

        return $this->cast($setting->value, $setting->type);
    }

    public function set(string $key, mixed $value, string $group = 'general', string $type = 'string', ?int $branchId = null, bool $isPublic = false): Setting
    {
        return Setting::updateOrCreate(
            ['branch_id' => $branchId, 'key' => $key],
            [
                'group' => $group,
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'type' => $type,
                'is_public' => $isPublic,
            ]
        );
    }

    private function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'json' => json_decode($value ?? '[]', true) ?: [],
            default => $value,
        };
    }
}
