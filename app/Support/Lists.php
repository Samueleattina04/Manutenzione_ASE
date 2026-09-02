<?php

namespace App\Support;

use App\Models\ListItem;

/**
 * Fornisce gli elenchi modificabili dall'amministratore (impianti, reparti).
 * Legge dal database; se la tabella non esiste ancora o è vuota, usa i valori
 * predefiniti in config/manutenzione.php.
 */
class Lists
{
    /** Cache a livello di richiesta per evitare query ripetute. */
    protected static array $cache = [];

    public static function values(string $type, string $configFallback): array
    {
        if (isset(static::$cache[$type])) {
            return static::$cache[$type];
        }

        try {
            $values = ListItem::where('type', $type)
                ->orderBy('position')->orderBy('id')
                ->pluck('value')->all();
        } catch (\Throwable $e) {
            $values = [];
        }

        if (empty($values)) {
            $values = array_values(config($configFallback, []));
        }

        return static::$cache[$type] = $values;
    }

    public static function impianti(): array
    {
        return static::values('impianto', 'manutenzione.impianti');
    }

    public static function reparti(): array
    {
        return static::values('reparto', 'manutenzione.reparti');
    }

    /** Svuota la cache di richiesta (dopo una modifica). */
    public static function flush(): void
    {
        static::$cache = [];
    }
}
