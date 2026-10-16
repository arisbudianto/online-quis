<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class GradeScale extends Model
{
    protected $fillable = ['letter', 'min_score', 'weight'];

    protected $casts = [
        'min_score' => 'float',
        'weight' => 'float',
    ];

    /** Urut dari batas tertinggi ke terendah. */
    public static function ordered(): Collection
    {
        return static::orderByDesc('min_score')->get();
    }

    /**
     * Huruf untuk satu nilai angka, dibandingkan apa adanya (tanpa
     * pembulatan): 84,99 masih A-, 85 baru A.
     */
    public static function resolve(float $score, ?Collection $scales = null): ?self
    {
        $scales ??= static::ordered();

        return $scales->first(fn (self $s) => $score >= $s->min_score);
    }
}
