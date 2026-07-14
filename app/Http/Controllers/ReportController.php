<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\GrowthMeasurement;
use App\Models\Student;
use App\Support\ChildBmiClassifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private const STATUSES = ChildBmiClassifier::STATUSES;

    public function index(Request $request)
    {
        return view('reports.index', $this->buildReportData($request));
    }

    private function buildReportData(Request $request): array
    {
        $user = auth()->user();
        $schoolId = $user?->school_id;
        $schoolName = $user?->school?->name ?? 'School Nutrition Program';
        $schoolYear = $request->input('school_year', '2025-2026');
        $grade = $request->input('grade', 'All');
        $section = $request->input('section', 'All');
        $status = $request->input('status', 'All');
        $dateRange = $request->input('date_range', $this->defaultDateRange($schoolYear));
        [$startDate, $endDate] = $this->parseDateRange($dateRange, $schoolYear);

        $students = Student::query()
            ->with('latestMeasurement')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($grade !== 'All', fn ($q) => $q->where('class_name', $grade))
            ->when($section !== 'All', fn ($q) => $q->where('section', $section))
            ->get();

        $students = $students->filter(function (Student $student) use ($status) {
            if ($status === 'All') {
                return true;
            }

            return ChildBmiClassifier::classifyForStudent($student, $student->latestMeasurement) === $status;
        })->values();

        $studentIds = $students->pluck('id');
        $totalStudents = $students->count();
        $statusCounts = array_fill_keys(self::STATUSES, 0);

        foreach ($students as $student) {
            $statusCounts[ChildBmiClassifier::classifyForStudent($student, $student->latestMeasurement)]++;
        }

        $months = $this->monthsBetween($startDate, $endDate);
        $trendLabels = $months->map(fn ($month) => $month->format('M Y'))->values();
        $bmiTrend = $months->map(function (Carbon $month) use ($studentIds) {
            if ($studentIds->isEmpty()) {
                return 0;
            }

            $avg = GrowthMeasurement::query()
                ->whereIn('student_id', $studentIds)
                ->whereBetween('measured_at', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->avg('bmi');

            return $avg ? round($avg, 1) : null;
        })->values();

        $screeningProgress = $months->map(function (Carbon $month) use ($studentIds) {
            if ($studentIds->isEmpty()) {
                return 0;
            }

            return GrowthMeasurement::query()
                ->whereIn('student_id', $studentIds)
                ->whereBetween('measured_at', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->distinct('student_id')
                ->count('student_id');
        })->values();

        $mealTotals = $this->mealTotals($studentIds, $startDate, $endDate);
        $studentMealTotals = $this->studentMealTotals($studentIds, $startDate, $endDate);
        $screenedStudents = GrowthMeasurement::query()
            ->whereIn('student_id', $studentIds)
            ->whereBetween('measured_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->distinct('student_id')
            ->count('student_id');

        $reportRows = $students->sortBy([['class_name', 'asc'], ['section', 'asc'], ['name', 'asc']])
            ->map(function (Student $student) use ($studentMealTotals) {
                $measurement = $student->latestMeasurement;
                $meals = $studentMealTotals->get($student->id);

                return [
                    'student' => $student,
                    'status' => ChildBmiClassifier::classifyForStudent($student, $measurement),
                    'measured_at' => optional($measurement?->measured_at)->format('Y-m-d'),
                    'bmi' => $measurement?->bmi ? round((float) $measurement->bmi, 1) : null,
                    'weight_kg' => $measurement?->weight_kg,
                    'height_cm' => $measurement?->height_cm,
                    'meals_served' => (int) ($meals->meals_served ?? 0),
                    'calories' => round((float) ($meals->calories ?? 0)),
                    'protein_g' => round((float) ($meals->protein_g ?? 0), 1),
                ];
            })->values();

        $classes = Student::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->select('class_name')
            ->distinct()
            ->pluck('class_name')
            ->sortBy(fn ($class) => $this->gradeSortValue($class))
            ->values();

        $sections = Student::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($grade !== 'All', fn ($q) => $q->where('class_name', $grade))
            ->select('section')
            ->distinct()
            ->orderBy('section')
            ->pluck('section')
            ->values();

        return [
            'schoolName' => $schoolName,
            'schoolYear' => $schoolYear,
            'selectedGrade' => $grade,
            'selectedSection' => $section,
            'selectedStatus' => $status,
            'dateRange' => $dateRange,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'classes' => $classes,
            'sections' => $sections,
            'totalStudents' => $totalStudents,
            'statusCounts' => $statusCounts,
            'trendLabels' => $trendLabels,
            'bmiTrend' => $bmiTrend,
            'screeningProgress' => $screeningProgress,
            'screenedStudents' => $screenedStudents,
            'mealTotals' => $mealTotals,
            'reportRows' => $reportRows,
            'generatedBy' => $user?->name ?? 'NutriFlow',
            'generatedAt' => now('Asia/Manila'),
        ];
    }

    private function gradeSortValue(?string $grade): int
    {
        if ($grade === 'Kinder') {
            return 0;
        }

        if (preg_match('/Grade\s+(\d+)/i', (string) $grade, $matches)) {
            return (int) $matches[1];
        }

        return 99;
    }

    private function defaultDateRange(string $schoolYear): string
    {
        if (preg_match('/^(\d{4})-(\d{4})$/', $schoolYear, $matches)) {
            return $matches[1].'-06-01 to '.$matches[2].'-05-31';
        }

        return now('Asia/Manila')->startOfMonth()->toDateString().' to '.now('Asia/Manila')->toDateString();
    }

    private function parseDateRange(?string $dateRange, string $schoolYear): array
    {
        $value = trim((string) $dateRange);

        try {
            if (preg_match('/^\s*(\d{4}-\d{2}-\d{2})\s+(?:to|-)\s+(\d{4}-\d{2}-\d{2})\s*$/i', $value, $matches)) {
                $start = Carbon::parse($matches[1], 'Asia/Manila')->startOfDay();
                $end = Carbon::parse($matches[2], 'Asia/Manila')->endOfDay();

                return $start->lte($end) ? [$start, $end] : [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $date = Carbon::parse($value, 'Asia/Manila');

                return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
            }
        } catch (\Throwable) {
            // Fall through to the school-year default.
        }

        $fallback = $this->defaultDateRange($schoolYear);

        return $fallback === $value
            ? [now('Asia/Manila')->startOfMonth(), now('Asia/Manila')->endOfMonth()]
            : $this->parseDateRange($fallback, $schoolYear);
    }

    private function monthsBetween(Carbon $startDate, Carbon $endDate)
    {
        $months = collect();
        $cursor = $startDate->copy()->startOfMonth();
        $last = $endDate->copy()->startOfMonth();

        while ($cursor->lte($last)) {
            $months->push($cursor->copy());
            $cursor->addMonth();
        }

        return $months;
    }

    private function mealTotals($studentIds, Carbon $startDate, Carbon $endDate): array
    {
        if ($studentIds->isEmpty()) {
            return ['meals_served' => 0, 'calories' => 0, 'protein_g' => 0, 'avg_calories' => 0, 'avg_protein_g' => 0];
        }

        $totals = DB::table('meal_items')
            ->join('foods', 'foods.id', '=', 'meal_items.food_id')
            ->join('meals', 'meals.id', '=', 'meal_items.meal_id')
            ->whereIn('meals.student_id', $studentIds)
            ->whereBetween('meals.served_at', [$startDate, $endDate])
            ->selectRaw('COUNT(DISTINCT meals.id) as meals_served')
            ->selectRaw('COALESCE(SUM(meal_items.quantity * foods.kcal), 0) as calories')
            ->selectRaw('COALESCE(SUM(meal_items.quantity * foods.protein_g), 0) as protein_g')
            ->first();

        $mealsServed = (int) ($totals->meals_served ?? 0);

        return [
            'meals_served' => $mealsServed,
            'calories' => round((float) ($totals->calories ?? 0)),
            'protein_g' => round((float) ($totals->protein_g ?? 0), 1),
            'avg_calories' => $mealsServed ? round(((float) $totals->calories) / $mealsServed) : 0,
            'avg_protein_g' => $mealsServed ? round(((float) $totals->protein_g) / $mealsServed, 1) : 0,
        ];
    }

    private function studentMealTotals($studentIds, Carbon $startDate, Carbon $endDate)
    {
        if ($studentIds->isEmpty()) {
            return collect();
        }

        return DB::table('meal_items')
            ->join('foods', 'foods.id', '=', 'meal_items.food_id')
            ->join('meals', 'meals.id', '=', 'meal_items.meal_id')
            ->whereIn('meals.student_id', $studentIds)
            ->whereBetween('meals.served_at', [$startDate, $endDate])
            ->groupBy('meals.student_id')
            ->select('meals.student_id')
            ->selectRaw('COUNT(DISTINCT meals.id) as meals_served')
            ->selectRaw('COALESCE(SUM(meal_items.quantity * foods.kcal), 0) as calories')
            ->selectRaw('COALESCE(SUM(meal_items.quantity * foods.protein_g), 0) as protein_g')
            ->get()
            ->keyBy('student_id');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $this->buildReportData($request);
        $filename = 'nutriflow-report-'.now()->format('Ymd').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['NutriFlow Nutrition Monitoring Report']);
            fputcsv($handle, ['School', $data['schoolName']]);
            fputcsv($handle, ['School Year', $data['schoolYear']]);
            fputcsv($handle, ['Coverage', $data['startDate']->toDateString().' to '.$data['endDate']->toDateString()]);
            fputcsv($handle, ['Generated By', $data['generatedBy']]);
            fputcsv($handle, ['Generated At', $data['generatedAt']->format('Y-m-d H:i')]);
            fputcsv($handle, []);
            fputcsv($handle, ['Student', 'Grade', 'Section', 'Status', 'Latest Measurement', 'BMI', 'Weight kg', 'Height cm', 'Meals Served', 'Calories', 'Protein g']);

            foreach ($data['reportRows'] as $row) {
                fputcsv($handle, [
                    $row['student']->name,
                    $row['student']->class_name,
                    $row['student']->section,
                    $row['status'],
                    $row['measured_at'] ?? '',
                    $row['bmi'] ?? '',
                    $row['weight_kg'] ?? '',
                    $row['height_cm'] ?? '',
                    $row['meals_served'],
                    $row['calories'],
                    $row['protein_g'],
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        return view('reports.print', $this->buildReportData($request));
    }
}
