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

    /**
     * Writes every default from config/education.php that is not in the settings table
     * yet (e.g. commission_level_4..7 after levels were added). Values an admin already
     * saved are never touched. Run by a migration, so `php artisan migrate` on deploy is enough.
     *
     * @return list<string> the keys that were added
     */
    public static function insertMissingDefaults(): array
    {
        $existing = static::query()->pluck('key')->all();
        $added = [];

        foreach (config('education.defaults') as $key => $value) {
            if (! in_array($key, $existing, true)) {
                static::query()->create(['key' => $key, 'value' => is_bool($value) ? (int) $value : $value]);
                $added[] = $key;
            }
        }

        Cache::forget(self::CACHE_KEY);

        return $added;
    }

    /** Number of upline levels that earn commission (and team depth shown everywhere). */
    public static function levelCount(): int
    {
        return (int) config('education.commission_levels');
    }

    /**
     * Commission PERCENT per level, keyed 1..N (e.g. [1 => 15.0, 2 => 9.0, ...]).
     * Each level earns this % of the member's paid total when their commission is released.
     */
    public static function commissionLevels(): array
    {
        $levels = [];
        for ($i = 1; $i <= static::levelCount(); $i++) {
            $levels[$i] = round((float) static::get("commission_level_{$i}", 0), 2);
        }

        return $levels;
    }

    /** USD a level earns for a member who paid $base: base × percent / 100, rounded to cents. */
    public static function commissionAmount(int $level, float $base): float
    {
        return round($base * (static::commissionLevels()[$level] ?? 0) / 100, 2);
    }

    public static function withdrawalMethods(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) static::get('withdrawal_methods')))));
    }
}
