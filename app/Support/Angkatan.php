<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Angkatan mahasiswa dari awalan NIM (config/angkatan.php).
 * NIM yang tidak cocok dengan awalan mana pun masuk "Lainnya".
 */
class Angkatan
{
    public const LAINNYA = 'lainnya';

    /** @return array<string, array<int, string>> tahun => awalan */
    public static function prefixes(): array
    {
        return config('angkatan.prefixes', []);
    }

    /** @return array<int, string> tahun, terbaru dulu */
    public static function years(): array
    {
        return array_map('strval', array_keys(self::prefixes()));
    }

    /** Pilihan filter: tahun lalu "Lainnya". @return array<string, string> nilai => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::years() as $year) {
            $options[$year] = $year;
        }
        $options[self::LAINNYA] = 'Lainnya';

        return $options;
    }

    public static function isValid(?string $value): bool
    {
        return $value !== null && array_key_exists($value, self::options());
    }

    public static function label(?string $value): string
    {
        return self::options()[$value] ?? '-';
    }

    /** Tahun angkatan untuk satu NIM, atau null bila tidak dikenali. */
    public static function detect(?string $nim): ?string
    {
        $nim = strtoupper(trim((string) $nim));
        if ($nim === '') {
            return null;
        }

        foreach (self::prefixes() as $year => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($nim, strtoupper($prefix))) {
                    return (string) $year;
                }
            }
        }

        return null;
    }

    /**
     * Batasi query users ke satu angkatan. Nilai tidak dikenal diabaikan.
     * Pakai UPPER() supaya NIM yang diketik huruf kecil (k3524...) ikut cocok
     * di MySQL maupun SQLite.
     */
    public static function apply(Builder $query, ?string $value, string $column = 'nim'): Builder
    {
        if (!self::isValid($value)) {
            return $query;
        }

        if ($value === self::LAINNYA) {
            return $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere(function ($q2) use ($column) {
                    foreach (self::allPrefixes() as $prefix) {
                        $q2->whereRaw("UPPER({$column}) NOT LIKE ?", [strtoupper($prefix) . '%']);
                    }
                });
            });
        }

        return $query->where(function ($q) use ($value, $column) {
            foreach (self::prefixes()[$value] as $prefix) {
                $q->orWhereRaw("UPPER({$column}) LIKE ?", [strtoupper($prefix) . '%']);
            }
        });
    }

    /** @return array<int, string> */
    private static function allPrefixes(): array
    {
        return array_merge(...array_values(self::prefixes()));
    }
}
