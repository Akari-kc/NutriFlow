<?php

namespace App\Http\Controllers;

use App\Models\GradeSection;
use App\Models\FeedingProgramEnrollment;
use App\Models\Student;
use App\Support\ChildBmiClassifier;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    private const REQUIRED_COLUMNS = [
        'learner_uid',
        'gender',
        'grade',
        'section',
    ];

    private const OPTIONAL_COLUMNS = [
        'source_learner_reference',
        'lrn',
        'first_name',
        'middle_initial',
        'last_name',
        'suffix',
        'birthdate',
        'allergies',
        'measured_at',
        'weight_kg',
        'height_cm',
        'assessment_phase',
        'source_nutrition_status',
        'height_for_age_status',
        'assessment_method',
        'source_record_reference',
        'school_year',
        'milk_consent',
        'four_ps_status',
        'previous_sbfp_beneficiary',
    ];

    private const TEMPLATE_COLUMNS = [
        'learner_uid',
        'source_learner_reference',
        'lrn',
        'first_name',
        'middle_initial',
        'last_name',
        'suffix',
        'gender',
        'birthdate',
        'grade',
        'section',
        'allergies',
        'measured_at',
        'weight_kg',
        'height_cm',
        'assessment_phase',
        'source_nutrition_status',
        'height_for_age_status',
        'assessment_method',
        'source_record_reference',
        'school_year',
        'milk_consent',
        'four_ps_status',
        'previous_sbfp_beneficiary',
    ];

    public function index()
    {
        return view('imports.students', [
            'requiredColumns' => self::REQUIRED_COLUMNS,
            'optionalColumns' => self::OPTIONAL_COLUMNS,
            'result' => session('student_import_result'),
        ]);
    }

    public function template(): StreamedResponse
    {
        $columns = self::TEMPLATE_COLUMNS;
        $sampleRows = [
            ['LEARNER-001', 'Source learner 1', '', 'Maria', 'S.', 'Santos', '', 'Female', '2016-08-12', 'Grade 3', 'A', '', '2025-07-14', '24.5', '126.4', 'Baseline', 'Wasted', 'Normal', 'Source report', 'report-row-1', '2025-2026', 'Yes', 'Yes', 'No'],
            ['LEARNER-001', 'Source learner 1', '', 'Maria', 'S.', 'Santos', '', 'Female', '2016-08-12', 'Grade 3', 'A', '', '2026-03-19', '25.1', '127.0', 'Endline', 'Normal', 'Normal', 'Source report', 'report-row-2', '2025-2026', 'Yes', 'Yes', 'No'],
            ['LEARNER-002', 'Source learner 2', '', '', '', '', '', 'Male', '', 'Grade 1', 'B', '', '2025-07-14', '20.0', '114.5', 'Baseline', 'Severely Wasted', 'Stunted', 'Source report', 'report-row-3', '2025-2026', 'Not recorded', 'No', 'Yes'],
        ];

        return response()->streamDownload(function () use ($columns, $sampleRows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($sampleRows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'learner-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $this->ensureGradeSectionsTable();

        $parsed = $this->readCsv($request->file('csv')->getRealPath());
        if ($parsed['errors']) {
            return back()->with('student_import_result', [
                'imported' => false,
                'summary' => ['created' => 0, 'updated' => 0, 'measurements' => 0, 'rows' => $parsed['row_count']],
                'errors' => $parsed['errors'],
            ]);
        }

        $validationErrors = $this->validateRows($parsed['rows']);
        if ($validationErrors) {
            return back()->with('student_import_result', [
                'imported' => false,
                'summary' => ['created' => 0, 'updated' => 0, 'measurements' => 0, 'rows' => $parsed['row_count']],
                'errors' => $validationErrors,
            ]);
        }

        $summary = DB::transaction(fn() => $this->saveRows($parsed['rows']));
        $summary['rows'] = $parsed['row_count'];

        return back()
            ->with('status', "Student import complete: {$summary['created']} created, {$summary['updated']} updated, {$summary['measurements']} growth records saved.")
            ->with('student_import_result', [
                'imported' => true,
                'summary' => $summary,
                'errors' => [],
            ]);
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return ['rows' => [], 'row_count' => 0, 'errors' => ['Could not read the uploaded CSV file.']];
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['rows' => [], 'row_count' => 0, 'errors' => ['The CSV file is empty or missing a header row.']];
        }

        $columns = collect($header)
            ->map(fn($column) => $this->normalizeHeader((string) $column))
            ->all();
        $errors = $this->missingColumns($columns);
        $rows = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->isBlankRow($row)) {
                continue;
            }

            $values = [];
            foreach ($columns as $index => $column) {
                if ($column !== '') {
                    $values[$column] = trim((string) ($row[$index] ?? ''));
                }
            }

            $values['_row'] = $rowNumber;
            $rows[] = $values;
        }

        fclose($handle);

        if (!$rows && !$errors) {
            $errors[] = 'The CSV has headers but no student rows.';
        }

        return ['rows' => $rows, 'row_count' => count($rows), 'errors' => $errors];
    }

    private function validateRows(array $rows): array
    {
        $errors = [];
        $validGrades = collect($this->gradeOptions())->mapWithKeys(fn($grade) => [strtolower($grade) => $grade]);

        foreach ($rows as $row) {
            $prefix = "Row {$row['_row']}: ";

            foreach (self::REQUIRED_COLUMNS as $column) {
                if (($row[$column] ?? '') === '') {
                    $errors[] = $prefix.$this->label($column).' is required.';
                }
            }

            if (($row['learner_uid'] ?? '') !== '' && !preg_match('/^LEARNER-\d{3,}$/', $row['learner_uid'])) {
                $errors[] = $prefix.'Learner UID must use the format LEARNER-001.';
            }

            if (($row['gender'] ?? '') !== '' && !$this->normalizeGender($row['gender'])) {
                $errors[] = $prefix.'Gender must be Male or Female.';
            }

            if (($row['birthdate'] ?? '') !== '') {
                try {
                    $birthdate = Carbon::parse($row['birthdate']);
                    $age = $birthdate->age;
                    if ($age < 4 || $age > 18) {
                        $errors[] = $prefix.'Birthdate must produce an age from 4 to 18 years old.';
                    }
                } catch (\Throwable) {
                    $errors[] = $prefix.'Birthdate must be a valid date.';
                }
            }

            if (($row['assessment_phase'] ?? '') !== ''
                && !in_array($row['assessment_phase'], ['Baseline', 'Midline', 'Endline', 'Additional Monitoring'], true)) {
                $errors[] = $prefix.'Assessment_phase must be Baseline, Midline, Endline, or Additional Monitoring.';
            }

            if (($row['source_nutrition_status'] ?? '') !== ''
                && $this->normalizeNutritionStatus($row['source_nutrition_status']) === null) {
                $errors[] = $prefix.'Source_nutrition_status must be Severely Wasted, Wasted, Normal, Overweight, or Obese.';
            }

            if (($row['grade'] ?? '') !== '' && !$validGrades->has(strtolower($row['grade']))) {
                $errors[] = $prefix.'Grade must be Kinder through Grade 6.';
            }

            $hasAnyGrowth = ($row['measured_at'] ?? '') !== '' || ($row['weight_kg'] ?? '') !== '' || ($row['height_cm'] ?? '') !== '';
            if ($hasAnyGrowth) {
                if (($row['measured_at'] ?? '') === '' || ($row['weight_kg'] ?? '') === '' || ($row['height_cm'] ?? '') === '') {
                    $errors[] = $prefix.'Growth records need measured_at, weight_kg, and height_cm together.';
                } else {
                    try {
                        Carbon::parse($row['measured_at']);
                    } catch (\Throwable) {
                        $errors[] = $prefix.'Measured_at must be a valid date.';
                    }

                    if (!$this->validNumber($row['weight_kg'], 1, 200)) {
                        $errors[] = $prefix.'Weight_kg must be between 1 and 200.';
                    }

                    if (!$this->validNumber($row['height_cm'], 30, 250)) {
                        $errors[] = $prefix.'Height_cm must be between 30 and 250.';
                    }
                }
            }
        }

        return array_slice($errors, 0, 100);
    }

    private function saveRows(array $rows): array
    {
        $schoolId = auth()->user()?->school_id;
        $seenStudents = [];
        $created = 0;
        $updated = 0;
        $measurements = 0;

        foreach ($rows as $row) {
            $learnerUid = strtoupper($row['learner_uid']);
            $student = Student::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                ->where('learner_uid', $learnerUid)
                ->first();

            $grade = $this->normalizeGrade($row['grade']);
            $section = trim($row['section']);
            $studentData = [
                'learner_uid' => $learnerUid,
                'source_learner_reference' => $this->nullable($row['source_learner_reference'] ?? ''),
                'lrn' => $this->nullable($row['lrn'] ?? ''),
                'name' => $this->composeName($row),
                'gender' => $this->normalizeGender($row['gender']),
                'birthdate' => ($row['birthdate'] ?? '') !== '' ? Carbon::parse($row['birthdate'])->format('Y-m-d') : null,
                'class_name' => $grade,
                'section' => $section,
                'allergies' => $this->normalizeAllergies($row['allergies'] ?? ''),
                'school_id' => $schoolId,
                'data_origin' => 'Imported',
            ];

            if ($student) {
                if (!isset($seenStudents[$learnerUid])) {
                    $student->update($studentData);
                    $updated++;
                }
            } else {
                $student = Student::create($studentData);
                $created++;
            }

            $seenStudents[$learnerUid] = true;
            $this->ensureGradeSection($schoolId, $grade, $section);
            $this->saveEnrollment($student, $row);

            if (($row['measured_at'] ?? '') !== '') {
                $measurements += $this->saveMeasurement($student, $row);
            }
        }

        return compact('created', 'updated', 'measurements');
    }

    private function saveMeasurement(Student $student, array $row): int
    {
        $measuredAt = Carbon::parse($row['measured_at']);
        $weightKg = round((float) $row['weight_kg'], 2);
        $heightCm = round((float) $row['height_cm'], 1);
        $heightM = $heightCm / 100;
        $bmi = round($weightKg / ($heightM * $heightM), 2);
        $rawSourceStatus = $this->nullable($row['source_nutrition_status'] ?? '');
        $sourceStatus = $this->normalizeNutritionStatus($rawSourceStatus ?? '');
        $bmiFlag = $sourceStatus
            ?? ($student->birthdate
                ? ChildBmiClassifier::classify($bmi, (string) $student->gender, $student->birthdate, $measuredAt)
                : ChildBmiClassifier::NEEDS_REVIEW);

        $student->measurements()->updateOrCreate(
            ['measured_at' => $measuredAt->format('Y-m-d')],
            [
                'weight_kg' => $weightKg,
                'height_cm' => $heightCm,
                'bmi' => $bmi,
                'bmi_flag' => $bmiFlag,
                'assessment_phase' => ($row['assessment_phase'] ?? '') ?: 'Additional Monitoring',
                'source_nutrition_status' => $rawSourceStatus,
                'height_for_age_status' => $this->nullable($row['height_for_age_status'] ?? ''),
                'assessment_method' => $this->nullable($row['assessment_method'] ?? '') ?? 'Imported source assessment',
                'data_origin' => 'Imported',
                'source_record_reference' => $this->nullable($row['source_record_reference'] ?? ''),
            ]
        );

        return 1;
    }

    private function missingColumns(array $columns): array
    {
        $missing = collect(self::REQUIRED_COLUMNS)
            ->reject(fn($column) => in_array($column, $columns, true))
            ->values();

        if ($missing->isEmpty()) {
            return [];
        }

        return ['Missing required columns: '.$missing->implode(', ').'.'];
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim(str_replace([' ', '-', '/'], '_', $header)));
    }

    private function isBlankRow(array $row): bool
    {
        return collect($row)->every(fn($value) => trim((string) $value) === '');
    }

    private function validNumber(string $value, float $min, float $max): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $number = (float) $value;
        return $number >= $min && $number <= $max;
    }

    private function label(string $column): string
    {
        return str_replace('_', ' ', ucfirst($column));
    }

    private function normalizeGender(string $gender): ?string
    {
        return match (strtolower(trim($gender))) {
            'male', 'm', 'boy' => 'Male',
            'female', 'f', 'girl' => 'Female',
            default => null,
        };
    }

    private function normalizeGrade(string $grade): string
    {
        $grade = strtolower(trim($grade));

        return collect($this->gradeOptions())
            ->first(fn($option) => strtolower($option) === $grade) ?? 'Grade 1';
    }

    private function normalizeAllergies(string $allergies): ?string
    {
        $value = collect(preg_split('/[,;\n]+/', $allergies))
            ->map(fn($allergy) => trim((string) $allergy))
            ->filter()
            ->unique()
            ->implode(', ');

        return $value !== '' ? $value : null;
    }

    private function composeName(array $row): ?string
    {
        $middle = trim((string) ($row['middle_initial'] ?? ''));
        if ($middle !== '' && !str_ends_with($middle, '.')) {
            $middle .= '.';
        }

        $name = collect([
            $row['first_name'] ?? '',
            $middle,
            $row['last_name'] ?? '',
            $row['suffix'] ?? '',
        ])->map(fn($part) => trim((string) $part))->filter()->implode(' ');

        return $name !== '' ? $name : null;
    }

    private function normalizeNutritionStatus(string $status): ?string
    {
        $value = strtolower(trim($status));

        $normalized = $value === '' ? null : ChildBmiClassifier::normalizeStatus($value);

        return in_array($normalized, [
            ChildBmiClassifier::SEVERELY_WASTED,
            ChildBmiClassifier::WASTED,
            ChildBmiClassifier::NORMAL,
            ChildBmiClassifier::OVERWEIGHT,
            ChildBmiClassifier::OBESE,
        ], true) ? $normalized : null;
    }

    private function saveEnrollment(Student $student, array $row): void
    {
        $schoolYear = $this->nullable($row['school_year'] ?? '');
        if (! $schoolYear) {
            return;
        }

        FeedingProgramEnrollment::updateOrCreate(
            ['student_id' => $student->id, 'school_year' => $schoolYear],
            [
                'program_name' => 'School-Based Feeding Program',
                'milk_consent' => $this->nullable($row['milk_consent'] ?? '') ?? 'Not recorded',
                'four_ps_status' => $this->nullable($row['four_ps_status'] ?? '') ?? 'Not recorded',
                'previous_sbfp_beneficiary' => $this->nullable($row['previous_sbfp_beneficiary'] ?? '') ?? 'Not recorded',
                'data_origin' => 'Imported',
            ]
        );
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function gradeOptions(): array
    {
        return ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'];
    }

    private function ensureGradeSection(?int $schoolId, string $className, string $section): void
    {
        if (!$this->ensureGradeSectionsTable() || trim($section) === '') {
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

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
