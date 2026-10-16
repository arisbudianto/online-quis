<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicPeriodController extends Controller
{
    public function index()
    {
        $periods = AcademicPeriod::withCount('quizzes')->latestFirst()->get();

        return view('admin.periods.index', [
            'periods' => $periods,
            'semesters' => AcademicPeriod::SEMESTERS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'year_start' => ['required', 'integer', 'min:2000', 'max:2100'],
            'semester' => [
                'required',
                Rule::in(array_keys(AcademicPeriod::SEMESTERS)),
                Rule::unique('academic_periods')->where('year_start', $request->input('year_start')),
            ],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'semester.unique' => 'Periode ini sudah ada.',
        ]);

        $period = AcademicPeriod::create([
            'year_start' => $data['year_start'],
            'semester' => $data['semester'],
        ]);

        if ($request->boolean('is_active') || !AcademicPeriod::active()) {
            $period->activate();
        }

        return back()->with('status', "Periode {$period->label} ditambahkan.");
    }

    public function activate(AcademicPeriod $period)
    {
        $period->activate();

        return back()->with('status', "Periode aktif sekarang {$period->label}. Kuis baru otomatis memakai periode ini.");
    }

    public function destroy(AcademicPeriod $period)
    {
        if ($period->quizzes()->exists() || CourseEnrollment::where('academic_period_id', $period->id)->exists()) {
            return back()->withErrors(['period' => "Periode {$period->label} sudah dipakai kuis atau daftar peserta, tidak bisa dihapus."]);
        }

        $period->delete();

        return back()->with('status', "Periode {$period->label} dihapus.");
    }
}
