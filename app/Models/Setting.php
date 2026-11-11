<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'education.settings';

    /** All settings: config defaults overlaid with admin-saved values. */
    public static function values(): array
    {
        $saved = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return array_merge(config('education.defaults'), $saved);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::values()[$key] ?? $default;
    }

    public static function money(string $key): float
    {
        return round((float) static::get($key, 0), 2);
    }

    public static function flag(string $key): bool
    {
        return filter_var(static::get($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => is_bool($value) ? (int) $value : $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** USD commission per level, keyed 1..N. */
    public static function commissionLevels(): array
    {
        $levels = [];
        for ($i = 1; $i <= config('education.commission_levels'); $i++) {
            $levels[$i] = static::money("commission_level_{$i}");
        }

        return $levels;
    }

    public static function withdrawalMethods(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) static::get('withdrawal_methods')))));
    }
}
