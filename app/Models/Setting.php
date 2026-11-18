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

    /** USD commission per level, keyed 1..N. */
    public static function commissionLevels(): array
    {
        $levels = [];
        for ($i = 1; $i <= static::levelCount(); $i++) {
            $levels[$i] = static::money("commission_level_{$i}");
        }

        return $levels;
    }

    public static function withdrawalMethods(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) static::get('withdrawal_methods')))));
    }
}
