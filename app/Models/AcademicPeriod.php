<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AcademicPeriod extends Model
{
    /**
     * Nilai yang diizinkan => label tampilan. Urutan di sini = urutan
     * semester dalam satu tahun ajaran.
     */
    public const SEMESTERS = [
        'ganjil' => 'Ganjil',
        'genap' => 'Genap',
    ];

    protected $fillable = ['year_start', 'semester', 'is_active'];

    protected $casts = [
        'year_start' => 'integer',
        'is_active' => 'boolean',
    ];

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    /** Contoh: "2026/2027 Ganjil" */
    public function getLabelAttribute(): string
    {
        $semester = self::SEMESTERS[$this->semester] ?? ucfirst((string) $this->semester);

        return $this->year_start . '/' . ($this->year_start + 1) . ' ' . $semester;
    }

    /** Terbaru di atas: tahun turun, lalu semester sesuai urutan SEMESTERS (terakhir dulu). */
    public function scopeLatestFirst(Builder $query): Builder
    {
        $order = array_keys(self::SEMESTERS);
        $case = 'CASE semester';
        foreach ($order as $i => $value) {
            $case .= " WHEN '" . $value . "' THEN " . $i;
        }
        $case .= ' ELSE ' . count($order) . ' END';

        return $query->orderByDesc('year_start')->orderByRaw($case . ' DESC');
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /** Jadikan periode ini satu-satunya yang aktif. */
    public function activate(): void
    {
        DB::transaction(function () {
            static::where('is_active', true)->where('id', '!=', $this->id)->update(['is_active' => false]);
            $this->update(['is_active' => true]);
        });
    }
}
