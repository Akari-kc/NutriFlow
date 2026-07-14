<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Student, Meal, MealItem, Food, GrowthMeasurement};
use App\DemoDataEnricher;
use App\Support\ChildBmiClassifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NutritionAideController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $schoolId = $user?->school_id;
        $schoolName = $user?->school?->name ?? 'School Nutrition Program';

        DemoDataEnricher::run($schoolId);

        $studentsQ = Student::query();
        if ($schoolId) { $studentsQ->where('school_id', $schoolId); }
        $studentCount = (int) $studentsQ->count();

            $avgCaloriesQ = MealItem::join('foods','foods.id','=','meal_items.food_id')
                ->join('meals','meals.id','=','meal_items.meal_id');
            if ($schoolId) {
                $avgCaloriesQ->join('students','students.id','=','meals.student_id')
                    ->where('students.school_id', $schoolId);
            }
            $avgCalories = $avgCaloriesQ
                ->where('meals.served_at','>=', Carbon::now('Asia/Manila')->subDays(7))
                ->avg(DB::raw('meal_items.quantity * foods.kcal')) ?? 0;

            $avgProteinQ = MealItem::join('foods','foods.id','=','meal_items.food_id')
                ->join('meals','meals.id','=','meal_items.meal_id');
            if ($schoolId) {
                $avgProteinQ->join('students','students.id','=','meals.student_id')
                    ->where('students.school_id', $schoolId);
            }
            $avgProtein = $avgProteinQ
                ->where('meals.served_at','>=', Carbon::now('Asia/Manila')->subDays(7))
                ->avg(DB::raw('meal_items.quantity * foods.protein_g')) ?? 0;

            $today = Carbon::today('Asia/Manila');
            $intakeByStudent = MealItem::select('meals.student_id', DB::raw('SUM(meal_items.quantity * foods.kcal) as kcal'))
                ->join('foods','foods.id','=','meal_items.food_id')
                ->join('meals','meals.id','=','meal_items.meal_id')
                ->when($schoolId, function($q) use ($schoolId){
                    $q->join('students','students.id','=','meals.student_id')
                      ->where('students.school_id', $schoolId);
                })
                ->whereBetween('meals.served_at', [$today, (clone $today)->endOfDay()])
                ->groupBy('meals.student_id')
                ->pluck('kcal','meals.student_id');

            $todayMealsCount = Meal::when($schoolId, fn($q)=> $q->whereHas('student', fn($qq)=>$qq->where('school_id',$schoolId)))
                ->whereBetween('served_at',[now('Asia/Manila')->startOfDay(), now('Asia/Manila')->endOfDay()])
                ->count();

            $bmiPeriod = in_array(request('bmi_period', 'month'), ['day', 'week', 'month'], true)
                ? request('bmi_period', 'month')
                : 'month';
            $bmiRows = GrowthMeasurement::query()
                ->select('growth_measurements.measured_at', 'growth_measurements.bmi')
                ->join('students','students.id','=','growth_measurements.student_id')
                ->whereNotNull('growth_measurements.bmi')
                ->when($schoolId, fn($q) => $q->where('students.school_id', $schoolId))
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
                $riskQueryStudents = Student::when($schoolId, fn($q)=>$q->where('school_id',$schoolId))
                    ->with('latestMeasurement')
                    ->get();
                $atRiskStudents = $riskQueryStudents
                    ->filter(fn($s)=> ChildBmiClassifier::isUndernourished(ChildBmiClassifier::classifyForStudent($s, $s->latestMeasurement)))
                    ->take(10)
                    ->values();
                $actualAtRiskCount = $riskQueryStudents
                    ->filter(fn($s)=> ChildBmiClassifier::isUndernourished(ChildBmiClassifier::classifyForStudent($s, $s->latestMeasurement)))
                    ->count();
                $atRiskPercent = $studentCount ? round(($actualAtRiskCount / $studentCount) * 100) : 0;
                $severeCount = $riskQueryStudents
                    ->filter(fn($s)=> ChildBmiClassifier::classifyForStudent($s, $s->latestMeasurement) === ChildBmiClassifier::SEVERELY_UNDERNOURISHED)
                    ->count();
                $moderateCount = max($actualAtRiskCount - $severeCount, 0);

                $kpis = [
                    'total_students' => $studentCount,
                    'at_risk_count' => $actualAtRiskCount,
                    'severe_count' => $severeCount,
                    'moderate_count' => $moderateCount,
                    'meals_today' => $todayMealsCount,
                    'at_risk_percent' => $atRiskPercent,
                    'avg_calories' => round($avgCalories),
                    'avg_protein' => round($avgProtein, 1),
                ];

            // Today meals (paginated)
            $tmPage = request()->query('tm_page', 1);
            $todayMeals = Meal::with('student','items.food')
                ->when($schoolId, fn($q)=> $q->whereHas('student', fn($qq)=>$qq->where('school_id',$schoolId)))
                ->whereBetween('served_at',[now('Asia/Manila')->startOfDay(), now('Asia/Manila')->endOfDay()])
                ->latest('served_at')
                ->paginate(5, ['*'], 'tm_page', $tmPage)
                ->withQueryString();

            // Meal suggestions: simple heuristic based on the school's current risk profile.
            $statuses = Student::when($schoolId, fn($q)=>$q->where('school_id',$schoolId))
                ->with('latestMeasurement')->get()
                ->map(fn($student) => ChildBmiClassifier::classifyForStudent($student, $student->latestMeasurement));
            $need = $statuses->contains(fn($status) => ChildBmiClassifier::isUndernourished($status)) ? 'Undernourished' : 'Balanced';
            $foodsQ = Food::select('id','name','portion','kcal','protein_g','carbs_g','fat_g');
            $foods = $schoolId ? (clone $foodsQ)->where('school_id',$schoolId)->get() : $foodsQ->get();
            if ($foods->isEmpty()) {
                $foods = Food::select('id','name','portion','kcal','protein_g','carbs_g','fat_g')->get();
            }
            $suggested = $need === 'Undernourished'
                ? $foods->sortByDesc('kcal')->filter(fn($f)=> (float)($f->protein_g ?? 0) >= 5)->take(5)
                : $foods->filter(fn($f)=> ($f->kcal ?? 0) >= 120 && ($f->kcal ?? 0) <= 400)->sortByDesc('protein_g')->take(5);

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
                'suggestion' => [ 'need' => $need, 'items' => $suggested ],
            ]);
    }
}
