<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeScale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeScaleController extends Controller
{
    public function index()
    {
        return view('admin.grade-scales.index', ['scales' => GradeScale::ordered()]);
    }

    /**
     * Huruf tetap; yang diubah hanya batas bawah dan bobot. Aturan:
     * - batas bawah turun tegas dari huruf ke huruf (tidak boleh sama/naik);
     * - bobot tidak boleh naik saat huruf turun;
     * - huruf terendah harus berbatas 0 supaya semua nilai punya huruf.
     */
    public function update(Request $request)
    {
        $scales = GradeScale::ordered();

        $data = $request->validate([
            'scales' => ['required', 'array'],
            'scales.*.min_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'scales.*.weight' => ['required', 'numeric', 'min:0', 'max:4'],
        ]);

        $input = collect($data['scales']);
        abort_unless($input->keys()->sort()->values()->all() === $scales->pluck('id')->sort()->values()->all(), 422);

        // Urutan huruf mengikuti urutan sekarang (A paling atas).
        $rows = $scales->map(fn ($s) => [
            'model' => $s,
            'min' => (float) $input[$s->id]['min_score'],
            'weight' => (float) $input[$s->id]['weight'],
        ])->values();

        for ($i = 1; $i < $rows->count(); $i++) {
            $upper = $rows[$i - 1];
            $lower = $rows[$i];
            if ($lower['min'] >= $upper['min']) {
                throw ValidationException::withMessages([
                    'scales' => "Batas bawah {$lower['model']->letter} harus lebih kecil dari {$upper['model']->letter}.",
                ]);
            }
            if ($lower['weight'] > $upper['weight']) {
                throw ValidationException::withMessages([
                    'scales' => "Bobot {$lower['model']->letter} tidak boleh lebih besar dari {$upper['model']->letter}.",
                ]);
            }
        }

        if ($rows->last()['min'] != 0) {
            throw ValidationException::withMessages([
                'scales' => "Batas bawah {$rows->last()['model']->letter} harus 0 supaya semua nilai punya huruf.",
            ]);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $row['model']->update(['min_score' => $row['min'], 'weight' => $row['weight']]);
            }
        });

        return back()->with('status', 'Tabel konversi nilai disimpan.');
    }
}
