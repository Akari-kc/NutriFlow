<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\GrowthMeasurement;
use App\Models\Meal;
use App\Models\GradeSection;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\ChildBmiClassifier;

class StudentController extends Controller
{
    public function index()
    {
        $schoolId = auth()->user()?->school_id;
        $mode = request('mode', 'all') === 'grade' ? 'grade' : 'all';
        $search = trim((string) request('q', ''));
        $class = request('class_name') ?: request('grade');
        $section = request('section');
        $risk = request('risk', 'All');
        $dir = strtolower(request('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Student::with('latestMeasurement');
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('class_name', 'like', "%{$search}%")
                    ->orWhere('section', 'like', "%{$search}%");
            });
        }
        if (!empty($class)) {
            $query->where('class_name', $class);
        }
        if (!empty($section)) {
            $query->where('section', $section);
        }
        $gradeStudents = (clone $query)
            ->orderBy('section')
            ->orderBy('name')
            ->get()
            ->filter(fn ($student) => $this->matchesRisk($student, $risk))
            ->sortBy([
                fn($a, $b) => $this->gradeSortValue($a->class_name) <=> $this->gradeSortValue($b->class_name),
                fn($a, $b) => strcmp((string) $a->class_name, (string) $b->class_name),
                fn($a, $b) => strcmp((string) $a->section, (string) $b->section),
                fn($a, $b) => strcmp((string) $a->name, (string) $b->name),
            ])
            ->values();

        $groupedStudents = $gradeStudents->groupBy(fn($student) => $student->class_name ?: 'Unassigned');
        $studentsByGradeSection = $gradeStudents
            ->groupBy(fn($student) => $student->class_name ?: 'Unassigned')
            ->map(fn($studentsInGrade) => $studentsInGrade->groupBy(fn($student) => $student->section ?: 'No Section'));

        $filteredStudents = $query->orderBy('name', $dir)
            ->get()
            ->filter(fn ($student) => $this->matchesRisk($student, $risk))
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $students = new LengthAwarePaginator(
            $filteredStudents->forPage($page, 10)->values(),
            $filteredStudents->count(),
            10,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $totalStudents = Student::when($schoolId, fn($q)=>$q->where('school_id',$schoolId))->count();
        $classes = $this->availableClasses($schoolId);
        $sections = $this->availableSections($schoolId);
        $gradeSections = $this->availableGradeSections($schoolId);
        $searchSuggestions = Student::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get(['name', 'class_name', 'section'])
            ->flatMap(fn($student) => [$student->name, $student->class_name, $student->section])
            ->filter()
            ->unique()
            ->values();

        return view('students.index', [
            'students' => $students,
            'groupedStudents' => $groupedStudents,
            'mode' => $mode,
            'search' => $search,
            'selectedGrade' => $class,
            'selectedRisk' => $risk,
            'totalStudents' => $totalStudents,
            'classes' => $classes,
            'sections' => $sections,
            'gradeSections' => $gradeSections,
            'studentsByGradeSection' => $studentsByGradeSection,
            'searchSuggestions' => $searchSuggestions,
        ]);
    }

    public function create()
    {
        $schoolId = auth()->user()?->school_id;

        return view('students.create', [
            'classes' => $this->availableClasses($schoolId),
            'sections' => $this->availableSections($schoolId),
            'gradeSections' => $this->availableGradeSections($schoolId),
            'allergyOptions' => $this->allergyOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = auth()->user()?->school_id;
        $classes = $this->availableClasses($schoolId);
        $sections = $this->availableSections($schoolId);
        $allergyOptions = $this->allergyOptions();

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'last_name' => 'required|string|max:100',
            'suffix' => 'nullable|string|max:20',
            'gender' => 'required|in:Male,Female',
            'birthdate' => 'required|date',
            'section' => ['required', 'string'],
            'section_new' => 'nullable|string|max:50',
            'class_name' => ['required', 'string', Rule::in($classes->all())],
            'allergies' => 'nullable|array',
            'allergies.*' => 'nullable|string|in:'.implode(',', array_merge($allergyOptions, ['Other'])),
            'allergy_other' => 'nullable|array',
            'allergy_other.*' => 'nullable|string|max:100',
            'weight_value' => 'required|numeric|min:0',
            'weight_unit' => 'required|in:kg,g,lb',
            'height_value' => 'required|numeric|min:0',
            'height_unit' => 'required|in:cm,m,in,ft',
        ]);

        $weightKg = $this->normalizeWeightKg((float) $data['weight_value'], $data['weight_unit']);
        $heightCm = $this->normalizeHeightCm((float) $data['height_value'], $data['height_unit']);

        if ($weightKg <= 0 || $weightKg > 200 || $heightCm < 30 || $heightCm > 250) {
            return back()
                ->withErrors(['weight_value' => 'Converted weight/height is outside the allowed range.'])
                ->withInput();
        }

        $section = $this->resolveSection($data['section'], $data['section_new'] ?? null, $sections, $data['class_name']);
        if ($section === '') {
            return back()
                ->withErrors(['section' => 'Choose an existing section or add a new one.'])
                ->withInput();
        }

        $this->ensureGradeSection($schoolId, $data['class_name'], $section);

        $allergies = $this->normalizeAllergies($data['allergies'] ?? [], $data['allergy_other'] ?? []);

        $student = Student::create([
            'name' => $this->composeStudentName(
                $data['first_name'],
                $data['middle_initial'] ?? null,
                $data['last_name'],
                $data['suffix'] ?? null
            ),
            'gender' => $data['gender'],
            'birthdate' => $data['birthdate'],
            'section' => $section,
            'class_name' => $data['class_name'],
            'allergies' => $allergies ?: null,
            'school_id' => $schoolId,
        ]);

        $heightM = $heightCm > 0 ? ($heightCm / 100) : null;
        $bmi = $heightM ? round($weightKg / ($heightM * $heightM), 2) : null;
        $measuredAt = Carbon::today();
        $bmiFlag = $bmi !== null
            ? ChildBmiClassifier::classify($bmi, $student->gender, $student->birthdate, $measuredAt)
            : ChildBmiClassifier::NO_MEASUREMENT;

        $student->measurements()->create([
            'measured_at' => $measuredAt->format('Y-m-d'),
            'weight_kg' => $weightKg,
            'height_cm' => $heightCm,
            'bmi' => $bmi,
            'bmi_flag' => $bmiFlag,
        ]);

        return redirect()->route('students.index')->with('status','Student added');
    }

    public function show(Student $student)
    {
        if (auth()->user()?->school_id && $student->school_id !== auth()->user()->school_id) {
            abort(403);
        }
        $measurements = $student->measurements()->orderBy('measured_at','asc')->take(20)->get();
        $latestMeal = Meal::with('items.food')
            ->where('student_id', $student->id)
            ->latest('served_at')
            ->first();

        $mealType = request('meal_type', 'All');
        $mealFrom = request('meal_from');
        $mealTo = request('meal_to');
        $mealSearch = trim((string) request('meal_q', ''));

        $mealHistory = Meal::with('items.food')
            ->where('student_id', $student->id)
            ->when($mealType && $mealType !== 'All', fn($q) => $q->where('meal_type', $mealType))
            ->when($mealFrom, fn($q) => $q->where('served_at', '>=', Carbon::parse($mealFrom, 'Asia/Manila')->startOfDay()))
            ->when($mealTo, fn($q) => $q->where('served_at', '<=', Carbon::parse($mealTo, 'Asia/Manila')->endOfDay()))
            ->when($mealSearch !== '', function ($q) use ($mealSearch) {
                $q->whereHas('items.food', fn($foodQ) => $foodQ->where('name', 'like', "%{$mealSearch}%"));
            })
            ->latest('served_at')
            ->paginate(5, ['*'], 'meal_page')
            ->withQueryString();

        $latest = $measurements->last();

        $bmiPeriod = in_array(request('bmi_period', 'month'), ['day', 'week', 'month'], true)
            ? request('bmi_period', 'month')
            : 'month';
        $bmiRows = $student->measurements()
            ->whereNotNull('bmi')
            ->orderBy('measured_at')
            ->get(['measured_at', 'bmi']);
        $bmiGrouped = $bmiRows->groupBy(function ($measurement) use ($bmiPeriod) {
            $date = Carbon::parse($measurement->measured_at, 'Asia/Manila');

            return match ($bmiPeriod) {
                'day' => $date->toDateString(),
                'week' => $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                default => $date->copy()->startOfMonth()->toDateString(),
            };
        })->sortKeys();

        $chartLabels = [];
        $chartBmi = [];
        $chartRecordCounts = [];
        foreach ($bmiGrouped as $key => $rows) {
            $date = Carbon::parse($key, 'Asia/Manila');
            $chartLabels[] = match ($bmiPeriod) {
                'day' => $date->format('M j, Y'),
                'week' => $date->format('M j').' - '.$date->copy()->addDays(6)->format('M j'),
                default => $date->format('M Y'),
            };
            $chartBmi[] = round((float) $rows->avg('bmi'), 2);
            $chartRecordCounts[] = $rows->count();
        }

        $bmiLow = count($chartBmi) ? min($chartBmi) : null;
        $bmiHigh = count($chartBmi) ? max($chartBmi) : null;
        $bmiChange = count($chartBmi) >= 2 ? round(end($chartBmi) - $chartBmi[0], 1) : 0;
        $allergies = collect(preg_split('/[,;\n]+/', (string) $student->allergies))
            ->map(fn($item) => trim($item))
            ->filter()
            ->values();
        $mealSearchSuggestions = Meal::with('items.food')
            ->where('student_id', $student->id)
            ->latest('served_at')
            ->take(50)
            ->get()
            ->flatMap(fn($meal) => $meal->items->pluck('food.name'))
            ->filter()
            ->unique()
            ->values();

        return view('students.show', [
            'student' => $student,
            'measurements' => $measurements,
            'latest' => $latest,
            'latestMeal' => $latestMeal,
            'mealHistory' => $mealHistory,
            'mealFilters' => [
                'meal_type' => $mealType,
                'meal_from' => $mealFrom,
                'meal_to' => $mealTo,
                'meal_q' => $mealSearch,
            ],
            'chartLabels' => $chartLabels,
            'chartBmi' => $chartBmi,
            'chartRecordCounts' => $chartRecordCounts,
            'bmiPeriod' => $bmiPeriod,
            'bmiTotalRecords' => $bmiRows->count(),
            'bmiLow' => $bmiLow,
            'bmiHigh' => $bmiHigh,
            'bmiChange' => $bmiChange,
            'allergies' => $allergies,
            'mealSearchSuggestions' => $mealSearchSuggestions,
        ]);
    }

    public function edit(Student $student)
    {
        if (auth()->user()?->school_id && $student->school_id !== auth()->user()->school_id) {
            abort(403);
        }

        return view('students.edit', [
            'student' => $student,
            'nameParts' => $this->splitStudentName($student->name),
            'allergyOptions' => $this->allergyOptions(),
            'selectedAllergies' => $this->splitAllergies($student->allergies),
            'classes' => $this->availableClasses(auth()->user()?->school_id),
            'sections' => $this->availableSections(auth()->user()?->school_id),
            'gradeSections' => $this->availableGradeSections(auth()->user()?->school_id),
        ]);
    }

    public function update(Request $request, Student $student)
    {
        if (auth()->user()?->school_id && $student->school_id !== auth()->user()->school_id) {
            abort(403);
        }
        $allergyOptions = $this->allergyOptions();
        $schoolId = auth()->user()?->school_id;
        $classes = $this->availableClasses($schoolId);
        $sections = $this->availableSections($schoolId);
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'last_name' => 'required|string|max:100',
            'suffix' => 'nullable|string|max:20',
            'gender' => 'nullable|in:Male,Female',
            'birthdate' => 'nullable|date',
            'section' => ['nullable', 'string'],
            'section_new' => 'nullable|string|max:50',
            'class_name' => ['nullable', 'string', Rule::in($classes->all())],
            'allergies' => 'nullable|array',
            'allergies.*' => 'nullable|string|in:'.implode(',', array_merge($allergyOptions, ['Other'])),
            'allergy_other' => 'nullable|array',
            'allergy_other.*' => 'nullable|string|max:100',
        ]);

        $section = $this->resolveSection($data['section'] ?? '', $data['section_new'] ?? null, $sections, $data['class_name'] ?? null);
        if (($data['section'] ?? '') !== '' && $section === '') {
            return back()
                ->withErrors(['section' => 'Choose an existing section or add a new one.'])
                ->withInput();
        }

        if (!empty($data['class_name']) && $section !== '') {
            $this->ensureGradeSection($schoolId, $data['class_name'], $section);
        }

        $allergies = $this->normalizeAllergies($data['allergies'] ?? [], $data['allergy_other'] ?? []);

        $student->update([
            'name' => $this->composeStudentName(
                $data['first_name'],
                $data['middle_initial'] ?? null,
                $data['last_name'],
                $data['suffix'] ?? null
            ),
            'gender' => $data['gender'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'section' => $section ?: null,
            'class_name' => $data['class_name'] ?? null,
            'allergies' => $allergies ?: null,
        ]);

        return redirect()->route('students.show', $student)->with('status','Student updated');
    }

    public function storeSection(Request $request)
    {
        $schoolId = auth()->user()?->school_id;
        $classes = $this->availableClasses($schoolId);

        $data = $request->validate([
            'class_name' => ['required', 'string', Rule::in($classes->all())],
            'section' => 'required|string|max:50',
        ]);

        $this->ensureGradeSection($schoolId, $data['class_name'], $data['section']);

        return redirect()
            ->route('students.index', ['mode' => 'grade'])
            ->with('status', "{$data['class_name']} Section {$data['section']} added");
    }

    public function destroy(Student $student)
    {
        if (auth()->user()?->school_id && $student->school_id !== auth()->user()->school_id) {
            abort(403);
        }
        $student->delete();
        return redirect()->route('students.index')->with('status','Student removed');
    }

    public function storeMeasurement(Request $request, Student $student)
    {
        if (auth()->user()?->school_id && $student->school_id !== auth()->user()->school_id) {
            abort(403);
        }

        $data = $request->validate([
            'measured_at' => 'required|date',
            'weight_value' => 'required|numeric|min:0',
            'weight_unit' => 'required|in:kg,g,lb',
            'height_value' => 'required|numeric|min:0',
            'height_unit' => 'required|in:cm,m,in,ft',
        ]);

        $weightKg = $this->normalizeWeightKg((float) $data['weight_value'], $data['weight_unit']);
        $heightCm = $this->normalizeHeightCm((float) $data['height_value'], $data['height_unit']);

        if ($weightKg <= 0 || $weightKg > 200 || $heightCm < 30 || $heightCm > 250) {
            return back()
                ->withErrors(['weight_value' => 'Converted weight/height is outside the allowed range.'])
                ->withInput();
        }

        $heightM = $heightCm > 0 ? ($heightCm / 100) : null;
        $bmi = $heightM ? round($weightKg / ($heightM * $heightM), 2) : null;
        $measuredAt = Carbon::parse($data['measured_at']);
        $bmiFlag = $bmi !== null
            ? ChildBmiClassifier::classify($bmi, $student->gender, $student->birthdate, $measuredAt)
            : ChildBmiClassifier::NO_MEASUREMENT;

        $student->measurements()->create([
            'measured_at' => $measuredAt->format('Y-m-d'),
            'weight_kg' => $weightKg,
            'height_cm' => $heightCm,
            'bmi' => $bmi,
            'bmi_flag' => $bmiFlag,
        ]);

    return redirect()->back()->with('status', 'Measurement saved');
    }

    private function normalizeWeightKg(float $value, string $unit): float
    {
        return round(match ($unit) {
            'g' => $value / 1000,
            'lb' => $value * 0.45359237,
            default => $value,
        }, 2);
    }

    private function normalizeHeightCm(float $value, string $unit): float
    {
        return round(match ($unit) {
            'm' => $value * 100,
            'in' => $value * 2.54,
            'ft' => $value * 30.48,
            default => $value,
        }, 1);
    }

    private function matchesRisk(Student $student, string $risk): bool
    {
        if ($risk === 'All' || $risk === '') {
            return true;
        }

        $status = ChildBmiClassifier::classifyForStudent($student, $student->latestMeasurement);
        $level = ChildBmiClassifier::riskLevel($status);

        return match ($risk) {
            'Severe' => $level === 'Severe',
            'Moderate', 'AtRisk', 'Underweight' => $level === 'Moderate',
            'Low', 'Normal' => $level === 'Low',
            default => true,
        };
    }

    private function availableClasses(?int $schoolId)
    {
        $existing = Student::when($schoolId, fn($q)=>$q->where('school_id',$schoolId))
            ->select('class_name')
            ->distinct()
            ->pluck('class_name')
            ->filter();

        return collect($this->gradeOptions())
            ->merge($existing)
            ->unique()
            ->sortBy(fn($grade) => sprintf('%03d %s', $this->gradeSortValue($grade), $grade))
            ->values();
    }

    private function availableSections(?int $schoolId)
    {
        $sectionsTableReady = $this->ensureGradeSectionsTable();
        $studentSections = Student::when($schoolId, fn($q)=>$q->where('school_id',$schoolId))
            ->select('section')
            ->distinct()
            ->pluck('section')
            ->filter();

        $savedSections = $sectionsTableReady
            ? GradeSection::when($schoolId, fn($q)=>$q->where('school_id', $schoolId))
                ->select('section')
                ->distinct()
                ->pluck('section')
                ->filter()
            : collect();

        return collect($this->defaultSectionOptions())
            ->merge($savedSections)
            ->merge($studentSections)
            ->unique()
            ->sortBy(fn($section) => sprintf('%03d %s', $this->gradeSortValue($section), $section))
            ->values();
    }

    private function availableGradeSections(?int $schoolId)
    {
        $sectionsTableReady = $this->ensureGradeSectionsTable();
        $base = collect($this->gradeOptions())
            ->mapWithKeys(fn($grade) => [$grade => collect($this->defaultSectionOptions())]);

        $saved = $sectionsTableReady
            ? GradeSection::when($schoolId, fn($q)=>$q->where('school_id', $schoolId))
                ->select('class_name', 'section')
                ->get()
            : collect();

        $fromStudents = Student::when($schoolId, fn($q)=>$q->where('school_id', $schoolId))
            ->select('class_name', 'section')
            ->whereNotNull('class_name')
            ->whereNotNull('section')
            ->distinct()
            ->get();

        collect($saved)->concat($fromStudents)->each(function ($item) use (&$base) {
            $grade = $item->class_name;
            if (!$base->has($grade)) {
                $base[$grade] = collect();
            }

            $base[$grade] = $base[$grade]->push($item->section);
        });

        return $base
            ->map(fn($sections) => $sections->filter()->unique()->sortBy(fn($section) => sprintf('%03d %s', $this->gradeSortValue($section), $section))->values())
            ->sortKeysUsing(function ($a, $b) {
                $gradeCompare = $this->gradeSortValue($a) <=> $this->gradeSortValue($b);

                return $gradeCompare !== 0 ? $gradeCompare : strcmp($a, $b);
            });
    }

    private function gradeSortValue(?string $grade): int
    {
        if (strcasecmp((string) $grade, 'Kinder') === 0) {
            return 0;
        }

        preg_match('/\d+/', (string) $grade, $matches);

        return isset($matches[0]) ? (int) $matches[0] : PHP_INT_MAX;
    }

    private function gradeOptions(): array
    {
        return ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'];
    }

    private function defaultSectionOptions(): array
    {
        return [];
    }

    private function resolveSection(?string $section, ?string $newSection, $availableSections, ?string $className = null): string
    {
        if ($section === '__new') {
            return trim((string) $newSection);
        }

        $section = trim((string) $section);
        if ($section === '') {
            return '';
        }

        if ($className) {
            return $this->availableGradeSections(auth()->user()?->school_id)
                ->get($className, collect())
                ->contains($section) ? $section : '';
        }

        return $availableSections->contains($section) ? $section : '';
    }

    private function ensureGradeSection(?int $schoolId, string $className, string $section): void
    {
        if (!$this->ensureGradeSectionsTable()) {
            return;
        }

        GradeSection::updateOrCreate(
            [
                'school_id' => $schoolId,
                'class_name' => $className,
                'section' => trim($section),
            ],
            []
        );
    }

    private function ensureGradeSectionsTable(): bool
    {
        try {
            if (Schema::hasTable('grade_sections')) {
                return true;
            }

            Schema::create('grade_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->nullable();
                $table->string('class_name');
                $table->string('section');
                $table->timestamps();
                $table->unique(['school_id', 'class_name', 'section']);
            });

            DB::table('students')
                ->select('school_id', 'class_name', 'section')
                ->whereNotNull('class_name')
                ->whereNotNull('section')
                ->distinct()
                ->get()
                ->each(function ($row) {
                    DB::table('grade_sections')->insertOrIgnore([
                        'school_id' => $row->school_id,
                        'class_name' => $row->class_name,
                        'section' => $row->section,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function normalizeAllergies(array $allergySelections, array $otherSelections): string
    {
        $otherValues = collect($otherSelections);

        return collect($allergySelections)
            ->map(function ($allergy, $index) use ($otherValues) {
                if ($allergy === 'Other') {
                    return trim((string) $otherValues->get($index, ''));
                }

                return $allergy;
            })
            ->map(fn($allergy) => trim((string) $allergy))
            ->filter(fn($allergy) => $allergy !== '')
            ->unique()
            ->values()
            ->implode(', ');
    }

    private function allergyOptions(): array
    {
        return [
            'Peanuts',
            'Tree nuts',
            'Almonds',
            'Cashews',
            'Walnuts',
            'Milk',
            'Eggs',
            'Soy',
            'Wheat/Gluten',
            'Fish',
            'Shellfish',
            'Shrimp',
            'Crab',
            'Sesame',
            'Lactose',
            'Corn',
            'Strawberries',
            'Banana',
            'Chocolate',
            'Food dye',
            'Sulfites',
        ];
    }

    private function splitAllergies(?string $allergies)
    {
        return collect(preg_split('/[,;\n]+/', (string) $allergies))
            ->map(fn($item) => trim($item))
            ->filter()
            ->values();
    }

    private function splitStudentName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $suffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];
        $suffix = '';

        if ($parts && in_array(end($parts), $suffixes, true)) {
            $suffix = array_pop($parts);
        }

        $first = array_shift($parts) ?? '';
        $middle = '';
        $last = '';

        if (count($parts) > 1 && strlen(rtrim($parts[0], '.')) <= 2) {
            $middle = array_shift($parts);
        }

        $last = implode(' ', $parts);

        if ($last === '' && $middle !== '') {
            $last = $middle;
            $middle = '';
        }

        return [
            'first_name' => $first,
            'middle_initial' => $middle,
            'last_name' => $last,
            'suffix' => $suffix,
        ];
    }

    private function composeStudentName(string $first, ?string $middleInitial, string $last, ?string $suffix): string
    {
        $middle = trim((string) $middleInitial);
        if ($middle !== '' && !str_ends_with($middle, '.')) {
            $middle .= '.';
        }

        return collect([
            trim($first),
            $middle,
            trim($last),
            trim((string) $suffix),
        ])->filter()->implode(' ');
    }
}
