<?php

namespace App\Libraries;

use Config\Database;
use Throwable;

/**
 * Lecture des réglages de la table app_settings (partagée avec la version 2.x).
 */
class Settings
{
    private static ?array $cache = null;

    public static function get(string $key, string $default = ''): string
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Database::connect()->table('app_settings')->get()->getResultArray() as $r) {
                    self::$cache[$r['setting_key']] = (string) $r['setting_value'];
                }
            } catch (Throwable) {
                // table absente : valeurs par défaut
            }
        }

        return (self::$cache[$key] ?? '') !== '' ? self::$cache[$key] : $default;
    }

    /** Écrit un réglage (même table et mêmes clés que la 2.x). */
    public static function set(string $key, string $value): void
    {
        $db  = Database::connect();
        $row = $db->table('app_settings')->select('id')->where('setting_key', $key)->get()->getRow();
        if ($row) {
            $db->table('app_settings')->where('id', $row->id)->update(['setting_value' => $value]);
        } else {
            $db->table('app_settings')->insert(['setting_key' => $key, 'setting_value' => $value]);
        }
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    public static function isStandalone(): bool
    {
        return self::get('app_mode', 'saas') === 'standalone';
    }

    public static function standaloneSiteId(): ?int
    {
        $row = Database::connect()->table('sites')->select('id')->where('active', 1)->orderBy('id')->limit(1)->get()->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    public static function appName(): string
    {
        return self::get('app_name', config('Fo')->appName);
    }

    /** Pour les tests. */
    public static function reset(): void
    {
        self::$cache = null;
    }
}
