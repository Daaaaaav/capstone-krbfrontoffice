<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AISettings extends Model
{
    protected $table    = 'ai_settings';
    protected $fillable = ['key', 'value', 'type', 'group', 'label', 'description'];

    private const CACHE_KEY = 'ai_settings_all';
    private const CACHE_TTL = 3600; 

    public function getCastedValueAttribute(): mixed
    {
        return match ($this->type) {
            'int'   => (int)   $this->value,
            'float' => (float) $this->value,
            'bool'  => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            default => $this->value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allCached();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function group(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(fn ($s) => [$s->key => $s->casted_value])
            ->toArray();
    }

    public static function set(string $key, mixed $value): void
    {
        static::where('key', $key)->update(['value' => (string) $value]);
        static::bustCache();
    }

    public static function getMultiple(array $defaults): array
    {
        $all    = static::allCached();
        $result = [];
        foreach ($defaults as $key => $default) {
            $result[$key] = array_key_exists($key, $all) ? $all[$key] : $default;
        }
        return $result;
    }

    public static function bustCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function allCached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return static::all()
                ->mapWithKeys(fn ($s) => [$s->key => $s->casted_value])
                ->toArray();
        });
    }
}
