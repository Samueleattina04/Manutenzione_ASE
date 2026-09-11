<?php

namespace App\Support;

use App\Models\Setting;

/** Impostazioni chiave/valore modificabili dall'amministratore. */
class Settings
{
    protected static array $cache = [];

    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        try {
            $value = Setting::where('key', $key)->value('value');
        } catch (\Throwable $e) {
            $value = null; // tabella non ancora migrata
        }

        return static::$cache[$key] = ($value ?? $default);
    }

    public static function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        static::$cache[$key] = $value;
    }
}
