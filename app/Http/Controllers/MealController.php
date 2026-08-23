<?php

namespace App\Http\Controllers;

use App\Models\FeedingSchedule;
use App\Models\Food;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MealController extends Controller
{
    public function index()
    {
        $this->ensureMealScheduleColumn();
        $query = Meal::with(['student', 'items.food', 'feedingSchedule']);
        $user = auth()->user();
        $schoolId = $user?->school_id;
        if ($user && $user->school_id) {
            $query->whereHas('student', function ($q) use ($user) {
                $q->where('school_id', $user->school_id);
            });
        }
        $filters = [
            'date_from' => request('date_from'),
            'date_to' => request('date_to'),
            'time_from' => request('time_from'),
            'time_to' => request('time_to'),
            'q' => trim((string) request('q', '')),
            'class_name' => request('class_name'),
            'section' => request('section'),
        ];

        $query
            ->when($filters['date_from'], fn ($q, $date) => $q->where('served_at', '>=', Carbon::parse($date, 'Asia/Manila')->startOfDay()))
            ->when($filters['date_to'], fn ($q, $date) => $q->where('served_at', '<=', Carbon::parse($date, 'Asia/Manila')->endOfDay()))
            ->when($filters['time_from'], fn ($q, $time) => $q->whereTime('served_at', '>=', $time))
            ->when($filters['time_to'], fn ($q, $time) => $q->whereTime('served_at', '<=', $time))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $q->whereHas('student', fn ($studentQ) => $studentQ->where('name', 'like', "%{$filters['q']}%"));
            })
            ->when($filters['class_name'] || $filters['section'], function ($q) use ($filters) {
                $q->whereHas('student', function ($studentQ) use ($filters) {
                    if ($filters['class_name']) {
                        $studentQ->where('class_name', $filters['class_name']);
                    }
                    if ($filters['section']) {
                        $studentQ->where('section', $filters['section']);
                    }
                });
            });

        $students = Student::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get(['id', 'name', 'class_name', 'section']);
        $studentSuggestions = $students->pluck('name')->unique()->values();
        $classes = Student::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->select('class_name')
            ->distinct()
            ->pluck('class_name')
            ->filter()
            ->sortBy(fn ($grade) => strcasecmp($grade, 'Kinder') === 0 ? 0 : (int) preg_replace('/\D+/', '', $grade))
            ->values();
        $sections = Student::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->select('section')
            ->distinct()
            ->pluck('section')
            ->filter()
            ->sort()
            ->values();
        $gradeSections = $this->gradeSections($schoolId);

        $meals = $query->latest('served_at')->paginate(20)->withQueryString();

        return view('meals.index', compact('meals', 'students', 'studentSuggestions', 'classes', 'sections', 'gradeSections', 'filters'));
    }

    public function batch()
    {
        $this->ensureMealScheduleColumn();
        $user = auth()->user();
        $studentsQ = Student::query();
        $foodsQ = Food::query();
        if ($user && $user->school_id) {
            $studentsQ->where('school_id', $user->school_id);
            $foodsQ->where('school_id', $user->school_id);
        }

        // Filters
        $className = request('class_name');
        $section = request('section');
        $search = trim((string) request('q', ''));
        if ($className) {
            $studentsQ->where('class_name', $className);
        }
        if ($section) {
            $studentsQ->where('section', $section);
        }
        if ($search !== '') {
            $studentsQ->where('name', 'like', "%{$search}%");
        }

        // Distinct lists for filters (scoped)
        $classes = Student::when($user && $user->school_id, fn ($q) => $q->where('school_id', $user->school_id))
            ->select('class_name')->distinct()->pluck('class_name')->filter()->values();
        $sections = Student::when($user && $user->school_id, fn ($q) => $q->where('school_id', $user->school_id))
            ->select('section')->distinct()->pluck('section')->filter()->values();
        $gradeSections = $this->gradeSections($user?->school_id);
        $studentSuggestions = Student::when($user && $user->school_id, fn ($q) => $q->where('school_id', $user->school_id))
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();
        $studentAssessmentData = Student::when($user && $user->school_id, fn ($q) => $q->where('school_id', $user->school_id))
            ->orderBy('name')
            ->get(['id', 'name', 'allergies'])
            ->mapWithKeys(fn (Student $student) => [(string) $student->id => [
                'name' => $student->name,
                'allergies' => $student->allergies,
            ]]);
        $schedules = FeedingSchedule::query()
            ->when($user?->school_id, fn ($q) => $q->where('school_id', $user->school_id))
            ->where('status', '!=', 'Cancelled')
            ->where(function ($query) {
                $query
                    ->whereDate('session_date', '>=', now('Asia/Manila')->toDateString())
                    ->orWhereDoesntHave('meals');
            })
            ->withCount('meals')
            ->orderByDesc('session_date')
            ->orderByDesc('start_time')
            ->get();
        $scheduleData = $schedules->mapWithKeys(function (FeedingSchedule $schedule) {
            $servedAt = Carbon::parse(
                $schedule->session_date->format('Y-m-d').' '.$schedule->start_time,
                'Asia/Manila'
            );

            return [(string) $schedule->id => [
                'participant_ids' => collect($schedule->participant_student_ids)->map(fn ($id) => (int) $id)->values(),
                'food_ids' => collect($schedule->selected_food_ids)->map(fn ($id) => (int) $id)->values(),
                'meal_type' => $schedule->meal_type,
                'served_at' => $servedAt->format('Y-m-d\\TH:i'),
            ]];
        });

        return view('meals.batch', [
            'students' => $studentsQ->orderBy('name')->paginate(30)->withQueryString(),
            'foods' => $foodsQ->orderBy('name')->get(),
            'classes' => $classes,
            'sections' => $sections,
            'gradeSections' => $gradeSections,
            'search' => $search,
            'studentSuggestions' => $studentSuggestions,
            'studentAssessmentData' => $studentAssessmentData,
            'schedules' => $schedules,
            'scheduleData' => $scheduleData,
            'nutritionPlanEnabled' => (bool) config('nutriflow_ml.enabled'),
        ]);
    }

    public function batchStore(Request $request)
    {
        $data = $request->validate([
            'feeding_schedule_id' => 'nullable|integer|exists:feeding_schedules,id',
            'meal_type' => 'required|in:Breakfast,Lunch,Snack,Dinner',
            'served_at' => 'required|date|before_or_equal:now',
            'served_students' => 'array',
            'served_students.*' => 'exists:students,id',
            'items' => 'required|array|min:1',
            'items.*.food_id' => 'required|exists:foods,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $userId = auth()->id();
        $servedIds = array_values(array_unique($data['served_students'] ?? []));
        if (empty($servedIds)) {
            return back()->with('status', 'No students selected.')->withInput();
        }

        $schoolId = auth()->user()?->school_id;
        $schedule = null;
        if (! empty($data['feeding_schedule_id'])) {
            $schedule = FeedingSchedule::findOrFail($data['feeding_schedule_id']);
            abort_if($schoolId && $schedule->school_id !== $schoolId, 403);

            if ($schedule->status === 'Cancelled') {
                throw ValidationException::withMessages([
                    'feeding_schedule_id' => 'A cancelled feeding session cannot be logged.',
                ]);
            }
            if ($schedule->session_date->isFuture()) {
                throw ValidationException::withMessages([
                    'feeding_schedule_id' => 'A future feeding session cannot be logged yet.',
                ]);
            }
            if (Meal::where('feeding_schedule_id', $schedule->id)->exists()) {
                throw ValidationException::withMessages([
                    'feeding_schedule_id' => 'This feeding session already has a meal log.',
                ]);
            }

            $scheduledStudentIds = collect($schedule->participant_student_ids)
                ->map(fn ($id) => (int) $id)
                ->unique();
            abort_unless(collect($servedIds)->every(fn ($id) => $scheduledStudentIds->contains((int) $id)), 403);
        }

        if ($schoolId) {
            $studentCount = Student::where('school_id', $schoolId)->whereIn('id', $servedIds)->count();
            $foodIds = collect($data['items'])->pluck('food_id')->unique()->values();
            $foodCount = Food::where('school_id', $schoolId)->whereIn('id', $foodIds)->count();

            abort_unless($studentCount === count($servedIds) && $foodCount === $foodIds->count(), 403);
        }

        // Parse served_at in Manila timezone
        $servedAt = \Illuminate\Support\Carbon::parse($data['served_at'], 'Asia/Manila');

        DB::transaction(function () use ($data, $servedIds, $userId, $servedAt, $schedule) {
            if ($schedule && Meal::where('feeding_schedule_id', $schedule->id)->exists()) {
                throw ValidationException::withMessages([
                    'feeding_schedule_id' => 'This feeding session already has a meal log.',
                ]);
            }

            foreach ($servedIds as $sid) {
                $meal = Meal::create([
                    'student_id' => $sid,
                    'logged_by_user_id' => $userId,
                    'feeding_schedule_id' => $schedule?->id,
                    'meal_type' => $data['meal_type'],
                    'served_at' => $servedAt,
                ]);
                foreach ($data['items'] as $it) {
                    MealItem::create([
                        'meal_id' => $meal->id,
                        'food_id' => $it['food_id'],
                        'quantity' => (int) $it['quantity'],
                        'portion_text' => null,
                    ]);
                }
            }

            $schedule?->update(['status' => 'Completed']);
        });

        return redirect()->route('meals.index')->with(
            'status',
            $schedule ? 'Scheduled feeding session logged successfully.' : 'Ad hoc meals logged successfully.'
        );
    }

    public function destroy(Meal $meal)
    {
        $user = auth()->user();
        if ($user && $user->school_id) {
            if ($meal->student?->school_id !== $user->school_id) {
                abort(403);
            }
        }
        $meal->delete();

        return redirect()->route('meals.index')->with('status', 'Meal log deleted');
    }

    private function gradeSections(?int $schoolId)
    {
        return Student::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->select('class_name', 'section')
            ->whereNotNull('class_name')
            ->whereNotNull('section')
            ->distinct()
            ->get()
            ->groupBy('class_name')
            ->map(fn ($rows) => $rows->pluck('section')->filter()->unique()->sort()->values());
    }

    private function ensureMealScheduleColumn(): void
    {
        if (Schema::hasTable('meals') && ! Schema::hasColumn('meals', 'feeding_schedule_id')) {
            Schema::table('meals', function (Blueprint $table) {
                $table->unsignedBigInteger('feeding_schedule_id')->nullable()->after('logged_by_user_id');
            });
        }
    }
}
