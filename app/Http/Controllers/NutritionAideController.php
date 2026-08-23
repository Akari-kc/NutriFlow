<?php

namespace App\Http\Controllers;

use App\Models\FeedingSchedule;
use App\Models\Food;
use App\Models\GrowthMeasurement;
use App\Models\Meal;
use App\Models\Student;
use App\Support\ChildBmiClassifier;
use Illuminate\Support\Carbon;

class NutritionAideController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $schoolId = $user?->school_id;
        $schoolName = $user?->school?->name ?? 'School Nutrition Program';
        $scheduleDate = $this->resolveScheduleDate(request('schedule_date'));

        $studentsQ = Student::query();
        if ($schoolId) {
            $studentsQ->where('school_id', $schoolId);
        }
        $studentCount = (int) $studentsQ->count();

        $todayMealsCount = Meal::when($schoolId, fn ($q) => $q->whereHas('student', fn ($qq) => $qq->where('school_id', $schoolId)))
            ->whereBetween('served_at', [now('Asia/Manila')->startOfDay(), now('Asia/Manila')->endOfDay()])
            ->count();

        $bmiPeriod = in_array(request('bmi_period', 'month'), ['day', 'week', 'month'], true)
            ? request('bmi_period', 'month')
            : 'month';
        $bmiRows = GrowthMeasurement::query()
            ->select('growth_measurements.measured_at', 'growth_measurements.bmi')
            ->join('students', 'students.id', '=', 'growth_measurements.student_id')
            ->whereNotNull('growth_measurements.bmi')
            ->when($schoolId, fn ($q) => $q->where('students.school_id', $schoolId))
            ->orderBy('growth_measurements.measured_at')
            ->get();

        $bmiGrouped = $bmiRows->groupBy(function ($row) use ($bmiPeriod) {
            $date = Carbon::parse($row->measured_at, 'Asia/Manila');

            return match ($bmiPeriod) {
                'day' => $date->toDateString(),
                'week' => $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                default => $date->copy()->startOfMonth()->toDateString(),
            };
        })->sortKeys();

        $bmiLabels = [];
        $bmiSeries = [];
        $bmiRecordCounts = [];
        foreach ($bmiGrouped as $key => $rows) {
            $date = Carbon::parse($key, 'Asia/Manila');
            $bmiLabels[] = match ($bmiPeriod) {
                'day' => $date->format('M j, Y'),
                'week' => $date->format('M j').' - '.$date->copy()->addDays(6)->format('M j'),
                default => $date->format('M Y'),
            };
            $bmiSeries[] = round((float) $rows->avg('bmi'), 2);
            $bmiRecordCounts[] = $rows->count();
        }

        $hasChartData = count($bmiSeries) > 0;
        $bmiDelta = count($bmiSeries) >= 2 ? round(end($bmiSeries) - $bmiSeries[0], 1) : 0;
        $bmiTotalRecords = $bmiRows->count();

        // Students at risk using WHO BMI-for-age classification.
        $riskQueryStudents = Student::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->with('latestMeasurement')
            ->get();
        $atRiskStudents = $riskQueryStudents
            ->filter(fn ($s) => ChildBmiClassifier::isUndernourished(ChildBmiClassifier::classifyForStudent($s, $s->latestMeasurement)))
            ->take(10)
            ->values();
        $actualAtRiskCount = $riskQueryStudents
            ->filter(fn ($s) => ChildBmiClassifier::isUndernourished(ChildBmiClassifier::classifyForStudent($s, $s->latestMeasurement)))
            ->count();
        $atRiskPercent = $studentCount ? round(($actualAtRiskCount / $studentCount) * 100) : 0;
        $severeCount = $riskQueryStudents
            ->filter(fn ($s) => ChildBmiClassifier::classifyForStudent($s, $s->latestMeasurement) === ChildBmiClassifier::SEVERELY_UNDERNOURISHED)
            ->count();
        $moderateCount = max($actualAtRiskCount - $severeCount, 0);

        $kpis = [
            'total_students' => $studentCount,
            'at_risk_count' => $actualAtRiskCount,
            'severe_count' => $severeCount,
            'moderate_count' => $moderateCount,
            'meals_today' => $todayMealsCount,
            'at_risk_percent' => $atRiskPercent,
        ];

        // Today meals (paginated)
        $tmPage = request()->query('tm_page', 1);
        $todayMeals = Meal::with('student', 'items.food')
            ->when($schoolId, fn ($q) => $q->whereHas('student', fn ($qq) => $qq->where('school_id', $schoolId)))
            ->whereBetween('served_at', [now('Asia/Manila')->startOfDay(), now('Asia/Manila')->endOfDay()])
            ->latest('served_at')
            ->paginate(5, ['*'], 'tm_page', $tmPage)
            ->withQueryString();

        $foodsQ = Food::select('id', 'name', 'portion');
        $foods = $schoolId ? (clone $foodsQ)->where('school_id', $schoolId)->get() : $foodsQ->get();
        if ($foods->isEmpty()) {
            $foods = Food::select('id', 'name', 'portion')->get();
        }
        $suggested = $foods->sortBy('name')->take(5);

        $scheduleViewData = $this->scheduleViewData($schoolId, $scheduleDate);

        return view('dashboards.aide', [
            'kpis' => $kpis,
            'schoolName' => $schoolName,
            'bmiLabels' => $bmiLabels,
            'bmiSeries' => $bmiSeries,
            'bmiRecordCounts' => $bmiRecordCounts,
            'bmiPeriod' => $bmiPeriod,
            'bmiTotalRecords' => $bmiTotalRecords,
            'bmiDelta' => $bmiDelta,
            'hasChartData' => $hasChartData,
            'atRiskStudents' => $atRiskStudents,
            'todayMeals' => $todayMeals,
            'suggestion' => ['items' => $suggested],
        ] + $scheduleViewData);
    }

    public function feedingSchedule()
    {
        $scheduleDate = $this->resolveScheduleDate(request('schedule_date'));

        return view(
            'dashboards.partials.feeding-schedule',
            $this->scheduleViewData(auth()->user()?->school_id, $scheduleDate),
        );
    }

    private function scheduleViewData(?int $schoolId, Carbon $scheduleDate): array
    {
        $dashboardSchedules = FeedingSchedule::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereDate('session_date', $scheduleDate->toDateString())
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        return [
            'dashboardSchedules' => $dashboardSchedules,
            'scheduleDate' => $scheduleDate,
            'previousScheduleDate' => $scheduleDate->copy()->subDay()->toDateString(),
            'nextScheduleDate' => $scheduleDate->copy()->addDay()->toDateString(),
            'todayScheduleDate' => Carbon::today('Asia/Manila')->toDateString(),
        ];
    }

    private function resolveScheduleDate(?string $value): Carbon
    {
        if (! $value) {
            return Carbon::today('Asia/Manila');
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value, 'Asia/Manila');

            if (! $date || $date->format('Y-m-d') !== $value) {
                return Carbon::today('Asia/Manila');
            }

            return $date->startOfDay();
        } catch (\Throwable) {
            return Carbon::today('Asia/Manila');
        }
    }
}
