<?php

namespace App;

use App\Models\FeedingSchedule;
use App\Models\Food;
use App\Models\GrowthMeasurement;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoDataEnricher
{
    public static function run(?int $schoolId): void
    {
        if (!self::ready($schoolId)) {
            return;
        }

        $expandedMarker = 'expanded_demo_data_v2_school_'.($schoolId ?: 'all');
        $julyMarker = 'july_daily_demo_data_v1_school_'.($schoolId ?: 'all');

        DB::transaction(function () use ($schoolId, $expandedMarker, $julyMarker) {
            $students = Student::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                ->with('latestMeasurement')
                ->orderBy('id')
                ->get();

            $foods = Food::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                ->orderBy('name')
                ->get();

            if ($students->isEmpty() || $foods->count() < 2) {
                return;
            }

            if (!DB::table('demo_data_markers')->where('marker_key', $expandedMarker)->exists()) {
                self::renameFirstNames($students);
                self::addGrowthHistory($students);
                self::addCompletedMealHistory($students, $foods, $schoolId);

                DB::table('demo_data_markers')->insert([
                    'marker_key' => $expandedMarker,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (!DB::table('demo_data_markers')->where('marker_key', $julyMarker)->exists()) {
                self::addJulyGrowthChecks($students);
                self::addJulyCompletedFeeding($students, $foods, $schoolId);

                DB::table('demo_data_markers')->insert([
                    'marker_key' => $julyMarker,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private static function renameFirstNames($students): void
    {
        $firstNames = [
            'Alon', 'Amihan', 'Andres', 'Angelica', 'Arnel', 'Bea', 'Benjie', 'Bianca', 'Bituin', 'Camille',
            'Cedric', 'Celina', 'Charmaine', 'Christian', 'Clarisse', 'Dalisay', 'Danilo', 'Dianne', 'Dominic', 'Elaine',
            'Elias', 'Eliza', 'Emilio', 'Fatima', 'Felix', 'Frances', 'Gabriel', 'Giselle', 'Hannah', 'Inigo',
            'Iris', 'Janelle', 'Jasper', 'Jericho', 'Jessa', 'Joaquin', 'Jonas', 'Joyce', 'Juliana', 'Katrina',
            'Kevin', 'Kiana', 'Lara', 'Lester', 'Lia', 'Lorenzo', 'Luis', 'Mae', 'Marco', 'Mariel',
            'Miguel', 'Mikaela', 'Nathaniel', 'Nicole', 'Noel', 'Patricia', 'Paulo', 'Rafael', 'Regina', 'Rica',
            'Rogelio', 'Roxanne', 'Samantha', 'Sandro', 'Sophia', 'Tala', 'Teresa', 'Theo', 'Trisha', 'Vince',
            'Yana', 'Ysabel', 'Zandro', 'Zia', 'Ariane', 'Benedict', 'Carmina', 'Darwin', 'Evangeline', 'Francis',
            'Gino', 'Hazel', 'Janine', 'Kenneth', 'Leah', 'Marlon', 'Nerissa', 'Oscar', 'Queenie', 'Renato',
            'Shaira', 'Tomas', 'Ulysses', 'Valerie', 'Wendel', 'Xandra', 'Yuri', 'Zenaida',
        ];

        $used = [];
        foreach ($students->values() as $index => $student) {
            $parts = preg_split('/\s+/', trim((string) $student->name)) ?: [];
            $tail = collect(array_slice($parts, 1))->filter()->implode(' ') ?: 'Learner '.($index + 1);
            $name = trim($firstNames[$index % count($firstNames)].' '.$tail);

            if (isset($used[strtolower($name)])) {
                $name = trim($firstNames[$index % count($firstNames)].' '.substr($firstNames[($index + 11) % count($firstNames)], 0, 1).'. '.$tail);
            }

            $used[strtolower($name)] = true;
            $student->update(['name' => $name]);
        }
    }

    private static function addGrowthHistory($students): void
    {
        foreach ($students as $index => $student) {
            $latest = $student->latestMeasurement;
            $height = (float) ($latest?->height_cm ?: 122 + ($index % 32));
            $currentBmi = (float) ($latest?->bmi ?: 16.4 + (($index % 10) * 0.35));
            $monthlyChange = $currentBmi < 14 ? -0.18 : ($currentBmi < 18.5 ? -0.08 : 0.06);

            for ($monthsAgo = 8; $monthsAgo >= 1; $monthsAgo--) {
                $date = Carbon::now('Asia/Manila')->startOfMonth()->subMonths($monthsAgo)->addDays(($index % 20) + 1);
                if (GrowthMeasurement::where('student_id', $student->id)->whereDate('measured_at', $date->toDateString())->exists()) {
                    continue;
                }

                $heightCm = round(max(92, $height - ($monthsAgo * 0.32)), 1);
                $bmi = round(max(11.5, $currentBmi - ($monthsAgo * $monthlyChange) + (((($index + $monthsAgo) % 5) - 2) * 0.09)), 2);
                $weightKg = round($bmi * (($heightCm / 100) ** 2), 2);

                GrowthMeasurement::create([
                    'student_id' => $student->id,
                    'measured_at' => $date->toDateString(),
                    'weight_kg' => $weightKg,
                    'height_cm' => $heightCm,
                    'bmi' => $bmi,
                    'bmi_flag' => $bmi < 18.5 ? 'Underweight' : ($bmi < 25 ? 'Normal' : ($bmi < 30 ? 'Overweight' : 'Obese')),
                ]);
            }
        }
    }

    private static function addJulyGrowthChecks($students): void
    {
        $start = Carbon::today('Asia/Manila')->startOfMonth();
        $end = Carbon::today('Asia/Manila');
        $totalDays = max(1, $start->diffInDays($end));

        foreach ($students as $index => $student) {
            $latest = $student->latestMeasurement;
            $targetHeight = (float) ($latest?->height_cm ?: 122 + ($index % 32));
            $targetBmi = (float) ($latest?->bmi ?: 16.4 + (($index % 10) * 0.35));

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if (GrowthMeasurement::where('student_id', $student->id)->whereDate('measured_at', $date->toDateString())->exists()) {
                    continue;
                }

                $remainingDays = $date->diffInDays($end);
                $progress = $date->diffInDays($start) / $totalDays;
                $heightCm = round(max(92, $targetHeight - ($remainingDays * 0.04)), 1);
                $bmiShift = ((($index + (int) $date->format('j')) % 5) - 2) * 0.03;
                $bmi = round(max(11.5, $targetBmi - ($remainingDays * 0.025) + $bmiShift + ($progress * 0.05)), 2);
                $weightKg = round($bmi * (($heightCm / 100) ** 2), 2);

                GrowthMeasurement::create([
                    'student_id' => $student->id,
                    'measured_at' => $date->toDateString(),
                    'weight_kg' => $weightKg,
                    'height_cm' => $heightCm,
                    'bmi' => $bmi,
                    'bmi_flag' => $bmi < 18.5 ? 'Underweight' : ($bmi < 25 ? 'Normal' : ($bmi < 30 ? 'Overweight' : 'Obese')),
                ]);
            }
        }
    }

    private static function addCompletedMealHistory($students, $foods, ?int $schoolId): void
    {
        $templates = [
            ['Breakfast', 'Breakfast Group A', '07:00', '07:30'],
            ['Lunch', 'Lunch Group A', '12:00', '12:30'],
            ['Snack', 'Snack Group B', '15:00', '15:20'],
        ];
        $studentIds = $students->pluck('id')->values();

        for ($daysAgo = 24; $daysAgo >= 1; $daysAgo--) {
            $date = Carbon::now('Asia/Manila')->subDays($daysAgo);

            foreach ($templates as $templateIndex => [$mealType, $groupName, $start, $end]) {
                if (($daysAgo + $templateIndex) % 3 === 0) {
                    continue;
                }

                $name = $groupName.' - '.$date->format('M d');
                if (FeedingSchedule::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                    ->where('batch_name', $name)
                    ->whereDate('session_date', $date->toDateString())
                    ->exists()) {
                    continue;
                }

                $participants = $studentIds->filter(fn($id, $i) => (($i + $daysAgo + $templateIndex) % 5) < 2)->take(52)->values();
                $menu = $foods->slice(($daysAgo + $templateIndex) % max(1, $foods->count() - 2), 3)->values();

                $schedule = FeedingSchedule::create([
                    'school_id' => $schoolId,
                    'batch_name' => $name,
                    'grade_range' => 'Selected students',
                    'participant_student_ids' => $participants->all(),
                    'selected_food_ids' => $menu->pluck('id')->all(),
                    'meal_type' => $mealType,
                    'status' => 'Completed',
                    'session_date' => $date->toDateString(),
                    'start_time' => $start,
                    'end_time' => $end,
                    'student_count' => $participants->count(),
                    'assigned_aide' => auth()->user()?->name ?: 'Isabel Greenfield',
                    'menu_items' => $menu->pluck('name')->implode(', '),
                    'notes' => 'Generated demo meal history.',
                ]);

                $servedAt = Carbon::parse($date->toDateString().' '.$start, 'Asia/Manila');
                foreach ($participants as $studentId) {
                    $meal = Meal::create([
                        'student_id' => $studentId,
                        'logged_by_user_id' => auth()->id(),
                        'feeding_schedule_id' => $schedule->id,
                        'meal_type' => $mealType,
                        'served_at' => $servedAt,
                    ]);

                    foreach ($menu as $food) {
                        MealItem::create([
                            'meal_id' => $meal->id,
                            'food_id' => $food->id,
                            'quantity' => 1,
                            'portion_text' => null,
                        ]);
                    }
                }
            }
        }
    }

    private static function addJulyCompletedFeeding($students, $foods, ?int $schoolId): void
    {
        $templates = [
            ['Breakfast', 'July Breakfast Group', '07:00', '07:30'],
            ['Lunch', 'July Lunch Group', '12:00', '12:30'],
            ['Snack', 'July Snack Group', '15:00', '15:20'],
        ];
        $studentIds = $students->pluck('id')->values();
        $start = Carbon::today('Asia/Manila')->startOfMonth();
        $end = Carbon::today('Asia/Manila');

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dayOffset = $start->diffInDays($date);

            foreach ($templates as $templateIndex => [$mealType, $groupName, $startTime, $endTime]) {
                $name = $groupName.' - '.$date->format('M d');
                if (FeedingSchedule::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                    ->where('batch_name', $name)
                    ->whereDate('session_date', $date->toDateString())
                    ->exists()) {
                    continue;
                }

                $participants = $studentIds
                    ->filter(fn($id, $i) => (($i + $dayOffset + $templateIndex) % 4) < 2)
                    ->take(70)
                    ->values();
                $menu = self::rotatingMenu($foods, $dayOffset + $templateIndex, 3);

                $schedule = FeedingSchedule::create([
                    'school_id' => $schoolId,
                    'batch_name' => $name,
                    'grade_range' => 'Selected students',
                    'participant_student_ids' => $participants->all(),
                    'selected_food_ids' => $menu->pluck('id')->all(),
                    'meal_type' => $mealType,
                    'status' => 'Completed',
                    'session_date' => $date->toDateString(),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'student_count' => $participants->count(),
                    'assigned_aide' => auth()->user()?->name ?: 'Isabel Greenfield',
                    'menu_items' => $menu->pluck('name')->implode(', '),
                    'notes' => 'Generated completed July feeding session.',
                ]);

                $servedAt = Carbon::parse($date->toDateString().' '.$startTime, 'Asia/Manila');
                foreach ($participants as $studentId) {
                    $meal = Meal::create([
                        'student_id' => $studentId,
                        'logged_by_user_id' => auth()->id(),
                        'feeding_schedule_id' => $schedule->id,
                        'meal_type' => $mealType,
                        'served_at' => $servedAt,
                    ]);

                    foreach ($menu as $food) {
                        MealItem::create([
                            'meal_id' => $meal->id,
                            'food_id' => $food->id,
                            'quantity' => 1,
                            'portion_text' => null,
                        ]);
                    }
                }
            }
        }
    }

    private static function rotatingMenu($foods, int $offset, int $count)
    {
        return collect(range(0, $count - 1))
            ->map(fn($step) => $foods[($offset + $step) % $foods->count()])
            ->values();
    }

    private static function ready(?int $schoolId): bool
    {
        try {
            if (!Schema::hasTable('demo_data_markers')) {
                Schema::create('demo_data_markers', function (Blueprint $table) {
                    $table->id();
                    $table->string('marker_key')->unique();
                    $table->timestamps();
                });
            }

            if (Schema::hasTable('meals') && !Schema::hasColumn('meals', 'feeding_schedule_id')) {
                Schema::table('meals', function (Blueprint $table) {
                    $table->unsignedBigInteger('feeding_schedule_id')->nullable()->after('logged_by_user_id');
                });
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
