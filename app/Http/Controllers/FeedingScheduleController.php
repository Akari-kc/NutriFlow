<?php

namespace App\Http\Controllers;

use App\Models\FeedingSchedule;
use App\Models\Food;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeedingScheduleController extends Controller
{
    public function index()
    {
        $this->ensureTable();
        $this->seedDefaults();
        $this->backfillExistingSchedules();
        $this->syncMissingCompletedMealLogs();

        $schoolId = auth()->user()?->school_id;
        $mode = request('mode', 'week') === 'month' ? 'month' : 'week';
        $anchor = request('date')
            ? Carbon::parse(request('date'), 'Asia/Manila')
            : Carbon::now('Asia/Manila');

        if ($mode === 'month') {
            $start = $anchor->copy()->startOfMonth();
            $end = $anchor->copy()->endOfMonth();
        } else {
            $start = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = $start->copy()->addDays(6)->endOfDay();
        }
        $previousDate = $mode === 'month' ? $anchor->copy()->subMonth()->toDateString() : $anchor->copy()->subWeek()->toDateString();
        $nextDate = $mode === 'month' ? $anchor->copy()->addMonth()->toDateString() : $anchor->copy()->addWeek()->toDateString();

        $filters = [
            'q' => trim((string) request('q', '')),
            'meal_type' => request('meal_type', 'All'),
            'status' => request('status', 'All'),
        ];

        $query = FeedingSchedule::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->whereDate('session_date', '>=', $start->toDateString())
            ->whereDate('session_date', '<=', $end->toDateString())
            ->when($filters['meal_type'] !== 'All', fn($q) => $q->where('meal_type', $filters['meal_type']))
            ->when($filters['status'] !== 'All', fn($q) => $q->where('status', $filters['status']));

        $sessions = $query
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        if ($filters['q'] !== '') {
            $needle = strtolower($filters['q']);
            $sessions = $sessions->filter(function ($session) use ($needle) {
                $haystack = strtolower(implode(' ', [
                    $session->batch_name,
                    $session->assigned_aide,
                    $session->menu_items,
                    implode(' ', $session->participantNames()),
                    implode(' ', $session->selectedFoodNames()),
                ]));

                return str_contains($haystack, $needle);
            })->values();
        }

        $groupedSessions = $sessions->groupBy(fn($session) => $session->session_date->toDateString());
        $students = Student::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('class_name')
            ->orderBy('section')
            ->orderBy('name')
            ->get(['id', 'name', 'class_name', 'section']);
        $foods = Food::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get(['id', 'name']);
        $classes = $students->pluck('class_name')->filter()->unique()->values();
        $sections = $students->pluck('section')->filter()->unique()->sort()->values();

        return view('feeding-schedules.index', [
            'sessions' => $sessions,
            'groupedSessions' => $groupedSessions,
            'mode' => $mode,
            'anchor' => $anchor,
            'start' => $start,
            'end' => $end,
            'previousDate' => $previousDate,
            'nextDate' => $nextDate,
            'filters' => $filters,
            'mealTypes' => ['Breakfast', 'Lunch', 'Snack', 'Dinner'],
            'statuses' => ['Scheduled', 'Ongoing', 'Completed', 'Cancelled'],
            'students' => $students,
            'foods' => $foods,
            'classes' => $classes,
            'sections' => $sections,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureTable();
        $this->ensureMealScheduleColumn();
        $data = $this->validated($request);
        $data['school_id'] = auth()->user()?->school_id;
        $data = $this->prepareScheduleData($data);
        $schedule = FeedingSchedule::create($data);
        $this->syncCompletedMealLogs($schedule);

        return redirect()
            ->route('feeding-schedules.index', ['mode' => 'week', 'date' => $schedule->session_date->toDateString()])
            ->with('status', 'Feeding session added.');
    }

    public function update(Request $request, FeedingSchedule $feedingSchedule)
    {
        $this->authorizeSchool($feedingSchedule);
        $this->ensureMealScheduleColumn();
        $feedingSchedule->update($this->prepareScheduleData($this->validated($request)));
        $this->syncCompletedMealLogs($feedingSchedule->fresh());

        return redirect()
            ->route('feeding-schedules.index', ['mode' => 'week', 'date' => $feedingSchedule->session_date->toDateString()])
            ->with('status', 'Feeding session updated.');
    }

    public function destroy(FeedingSchedule $feedingSchedule)
    {
        $this->authorizeSchool($feedingSchedule);
        $this->ensureMealScheduleColumn();
        Meal::where('feeding_schedule_id', $feedingSchedule->id)->delete();
        $feedingSchedule->delete();

        return back()->with('status', 'Feeding session removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'session_name' => 'required|string|max:80',
            'meal_type' => 'required|in:Breakfast,Lunch,Snack,Dinner',
            'status' => 'required|in:Scheduled,Ongoing,Completed,Cancelled',
            'session_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'assigned_aide' => 'nullable|string|max:120',
            'participant_student_ids' => 'required|array|min:1',
            'participant_student_ids.*' => 'integer|exists:students,id',
            'selected_food_ids' => 'required|array|min:1',
            'selected_food_ids.*' => 'integer|exists:foods,id',
            'notes' => 'nullable|string|max:1000',
        ]);
    }

    private function prepareScheduleData(array $data): array
    {
        $studentIds = collect($data['participant_student_ids'])->map(fn($id) => (int) $id)->unique()->values();
        $foodIds = collect($data['selected_food_ids'])->map(fn($id) => (int) $id)->unique()->values();

        $students = Student::whereIn('id', $studentIds)->orderBy('name')->get(['name', 'class_name', 'section']);
        $foods = Food::whereIn('id', $foodIds)->orderBy('name')->pluck('name')->values();

        $data['participant_student_ids'] = $studentIds->all();
        $data['selected_food_ids'] = $foodIds->all();
        $data['student_count'] = $studentIds->count();
        $data['batch_name'] = trim($data['session_name']);
        unset($data['session_name']);
        $data['grade_range'] = $students
            ->map(fn($student) => trim(($student->class_name ?? '').' '.$student->section))
            ->filter()
            ->unique()
            ->implode(', ');
        $data['menu_items'] = $foods->implode(', ');

        return $data;
    }

    private function authorizeSchool(FeedingSchedule $schedule): void
    {
        if (auth()->user()?->school_id && $schedule->school_id !== auth()->user()->school_id) {
            abort(403);
        }
    }

    private function ensureTable(): void
    {
        if (!Schema::hasTable('feeding_schedules')) {
            Schema::create('feeding_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->nullable();
                $table->string('batch_name');
                $table->string('grade_range');
                $table->text('participant_student_ids')->nullable();
                $table->text('selected_food_ids')->nullable();
                $table->string('meal_type');
                $table->string('status')->default('Scheduled');
                $table->date('session_date');
                $table->time('start_time');
                $table->time('end_time');
                $table->unsignedInteger('student_count')->default(0);
                $table->string('assigned_aide')->nullable();
                $table->text('menu_items')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['school_id', 'session_date']);
            });

            return;
        }

        if (!Schema::hasColumn('feeding_schedules', 'participant_student_ids')) {
            Schema::table('feeding_schedules', function (Blueprint $table) {
                $table->text('participant_student_ids')->nullable()->after('grade_range');
            });
        }

        if (!Schema::hasColumn('feeding_schedules', 'selected_food_ids')) {
            Schema::table('feeding_schedules', function (Blueprint $table) {
                $table->text('selected_food_ids')->nullable()->after('participant_student_ids');
            });
        }
    }

    private function ensureMealScheduleColumn(): void
    {
        if (Schema::hasTable('meals') && !Schema::hasColumn('meals', 'feeding_schedule_id')) {
            Schema::table('meals', function (Blueprint $table) {
                $table->unsignedBigInteger('feeding_schedule_id')->nullable()->after('logged_by_user_id');
            });
        }
    }

    private function syncMissingCompletedMealLogs(): void
    {
        $this->ensureMealScheduleColumn();
        $schoolId = auth()->user()?->school_id;

        FeedingSchedule::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->where('status', 'Completed')
            ->get()
            ->each(function ($schedule) {
                if (!Meal::where('feeding_schedule_id', $schedule->id)->exists()) {
                    $this->syncCompletedMealLogs($schedule);
                }
            });
    }

    private function syncCompletedMealLogs(FeedingSchedule $schedule): void
    {
        if (!$schedule) {
            return;
        }

        $this->ensureMealScheduleColumn();

        DB::transaction(function () use ($schedule) {
            Meal::where('feeding_schedule_id', $schedule->id)->delete();

            if ($schedule->status !== 'Completed') {
                return;
            }

            $studentIds = collect($schedule->participant_student_ids)->filter()->map(fn($id) => (int) $id)->unique()->values();
            $foodIds = collect($schedule->selected_food_ids)->filter()->map(fn($id) => (int) $id)->unique()->values();

            if ($studentIds->isEmpty() || $foodIds->isEmpty()) {
                return;
            }

            $servedAt = Carbon::parse($schedule->session_date->format('Y-m-d').' '.$schedule->start_time, 'Asia/Manila');
            $userId = auth()->id();

            foreach ($studentIds as $studentId) {
                $meal = Meal::create([
                    'student_id' => $studentId,
                    'logged_by_user_id' => $userId,
                    'feeding_schedule_id' => $schedule->id,
                    'meal_type' => $schedule->meal_type,
                    'served_at' => $servedAt,
                ]);

                foreach ($foodIds as $foodId) {
                    MealItem::create([
                        'meal_id' => $meal->id,
                        'food_id' => $foodId,
                        'quantity' => 1,
                        'portion_text' => null,
                    ]);
                }
            }
        });
    }

    private function backfillExistingSchedules(): void
    {
        $schoolId = auth()->user()?->school_id;
        $students = Student::when($schoolId, fn($q) => $q->where('school_id', $schoolId))->orderBy('name')->get();
        $foods = Food::when($schoolId, fn($q) => $q->where('school_id', $schoolId))->orderBy('name')->get();

        FeedingSchedule::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->whereNull('participant_student_ids')
            ->get()
            ->each(function ($schedule) use ($students, $foods) {
                $studentIds = $this->studentsForLegacyGradeRange($students, (string) $schedule->grade_range)
                    ->take(max(1, (int) $schedule->student_count))
                    ->pluck('id')
                    ->values();
                $foodIds = $this->foodsForLegacyMenu($foods, (string) $schedule->menu_items);

                $schedule->update([
                    'participant_student_ids' => $studentIds->all(),
                    'selected_food_ids' => $foodIds->all(),
                    'student_count' => $studentIds->count(),
                ]);
            });
    }

    private function seedDefaults(): void
    {
        $schoolId = auth()->user()?->school_id;
        if (FeedingSchedule::when($schoolId, fn($q) => $q->where('school_id', $schoolId))->exists()) {
            return;
        }

        $base = Carbon::now('Asia/Manila')->startOfWeek()->addDays(4);
        $students = Student::when($schoolId, fn($q) => $q->where('school_id', $schoolId))->orderBy('name')->get();
        $foods = Food::when($schoolId, fn($q) => $q->where('school_id', $schoolId))->orderBy('name')->get();
        $defaults = [
            ['Breakfast', 'Batch A', 'Grades 1-2', '07:00', '07:30', 82, 'I. Greenfield', 'Arroz Caldo, Fresh Milk, Banana', 'Completed', 0],
            ['Breakfast', 'Batch B', 'Grades 3-4', '07:30', '08:00', 96, 'M. Santos', 'Pandesal, Boiled Egg, Orange Juice', 'Completed', 0],
            ['Lunch', 'Batch A', 'Grades 1-2', '12:00', '12:30', 80, 'I. Greenfield', 'Chicken Adobo, Steamed Rice, Vegetable Soup', 'Completed', 0],
            ['Lunch', 'Batch B', 'Grades 3-4', '12:30', '13:00', 94, 'M. Santos', 'Pancit Bihon, Steamed Rice, Fruit Salad', 'Ongoing', 0],
            ['Lunch', 'Batch C', 'Grades 5-6', '13:00', '13:30', 78, 'R. Dela Cruz', 'Beef Nilaga, Steamed Rice, Banana', 'Scheduled', 0],
            ['Breakfast', 'Batch A', 'Grades 1-2', '07:00', '07:30', 82, 'I. Greenfield', 'Champorado, Fresh Milk', 'Scheduled', 1],
            ['Lunch', 'Batch A', 'Grades 1-2', '12:00', '12:30', 80, 'I. Greenfield', 'Tinola, Steamed Rice, Papaya', 'Scheduled', 1],
        ];

        foreach ($defaults as [$meal, $batch, $grades, $start, $end, $count, $aide, $menu, $status, $offset]) {
            $studentIds = $this->studentsForLegacyGradeRange($students, $grades)->take($count)->pluck('id')->values();
            $foodIds = $this->foodsForLegacyMenu($foods, $menu);
            FeedingSchedule::create([
                'school_id' => $schoolId,
                'meal_type' => $meal,
                'batch_name' => $batch,
                'grade_range' => $grades,
                'participant_student_ids' => $studentIds->all(),
                'selected_food_ids' => $foodIds->all(),
                'start_time' => $start,
                'end_time' => $end,
                'student_count' => $studentIds->count(),
                'assigned_aide' => $aide,
                'menu_items' => $menu,
                'status' => $status,
                'session_date' => $base->copy()->addDays($offset)->toDateString(),
            ]);
        }
    }

    private function studentsForLegacyGradeRange($students, string $grades)
    {
        return $students->filter(function ($student) use ($grades) {
            if (str_contains($grades, '1-2')) {
                return in_array($student->class_name, ['Grade 1', 'Grade 2'], true);
            }

            if (str_contains($grades, '3-4')) {
                return in_array($student->class_name, ['Grade 3', 'Grade 4'], true);
            }

            if (str_contains($grades, '5-6')) {
                return in_array($student->class_name, ['Grade 5', 'Grade 6'], true);
            }

            return true;
        });
    }

    private function foodsForLegacyMenu($foods, string $menu)
    {
        $menuParts = collect(explode(',', $menu))->map(fn($name) => strtolower(trim($name)))->filter();
        $foodIds = $foods
            ->filter(fn($food) => $menuParts->contains(fn($name) => str_contains(strtolower($food->name), $name) || str_contains($name, strtolower($food->name))))
            ->pluck('id')
            ->values();

        return $foodIds->isNotEmpty() ? $foodIds : $foods->take(3)->pluck('id')->values();
    }
}
