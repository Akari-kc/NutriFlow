<?php

namespace Database\Seeders;

use App\Models\GrowthMeasurement;
use App\Models\School;
use App\Models\Student;
use App\Support\ChildBmiClassifier;
use App\Support\SyntheticGrowthProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BackfillSyntheticGrowthHistorySeeder extends Seeder
{
    private const TARGET_DISTINCT_DATES = 5;

    private const DAYS_BETWEEN_RECORDS = 30;

    public function run(): void
    {
        $school = School::where('name', 'Eulogio Rodriguez Integrated School')->first();
        if (! $school) {
            throw new RuntimeException('The synthetic NutriFlow school was not found.');
        }

        $students = Student::query()
            ->where('school_id', $school->id)
            ->with(['measurements' => fn ($query) => $query
                ->whereNotNull('measured_at')
                ->whereNotNull('weight_kg')
                ->whereNotNull('height_cm')
                ->whereNotNull('bmi')
                ->where('weight_kg', '>', 0)
                ->where('height_cm', '>', 0)
                ->where('bmi', '>', 0)
                ->orderBy('measured_at')
                ->orderBy('id')])
            ->orderBy('id')
            ->get();

        $createdRecords = 0;
        $backfilledChildren = 0;
        $skippedChildren = 0;

        DB::transaction(function () use (
            $students,
            &$createdRecords,
            &$backfilledChildren,
            &$skippedChildren
        ) {
            foreach ($students as $student) {
                $existingDates = $student->measurements
                    ->mapWithKeys(fn (GrowthMeasurement $measurement) => [
                        $measurement->measured_at->toDateString() => true,
                    ])
                    ->all();

                if (count($existingDates) >= self::TARGET_DISTINCT_DATES) {
                    continue;
                }

                $anchorDateString = array_key_first($existingDates);
                if (! $anchorDateString || ! $student->birthdate) {
                    $skippedChildren++;

                    continue;
                }

                $anchor = $student->measurements
                    ->filter(fn (GrowthMeasurement $measurement) => $measurement->measured_at->toDateString() === $anchorDateString)
                    ->sortBy([
                        ['height_cm', 'asc'],
                        ['id', 'asc'],
                    ])
                    ->first();
                $targetStatus = ChildBmiClassifier::classifyForStudent($student, $anchor);

                if (! in_array($targetStatus, [
                    ChildBmiClassifier::NORMAL,
                    ChildBmiClassifier::UNDERNOURISHED,
                    ChildBmiClassifier::SEVERELY_UNDERNOURISHED,
                ], true)) {
                    $skippedChildren++;

                    continue;
                }

                $anchorDate = Carbon::parse($anchorDateString, 'Asia/Manila')->startOfDay();
                $birthdate = Carbon::parse($student->birthdate, 'Asia/Manila')->startOfDay();
                $createdForChild = 0;
                $interval = 1;

                while (count($existingDates) < self::TARGET_DISTINCT_DATES) {
                    $measuredAt = $anchorDate->copy()->subDays($interval * self::DAYS_BETWEEN_RECORDS);
                    $dateString = $measuredAt->toDateString();

                    if ($measuredAt->lte($birthdate)) {
                        $skippedChildren++;

                        break;
                    }

                    if (isset($existingDates[$dateString])) {
                        $interval++;

                        continue;
                    }

                    $height = round(max(40, (float) $anchor->height_cm - ($interval * 0.4)), 2);
                    $progress = max(0, 1 - ($interval / self::TARGET_DISTINCT_DATES));
                    $values = SyntheticGrowthProfile::measurementValues(
                        $student,
                        $measuredAt,
                        $height,
                        $targetStatus,
                        $progress
                    );

                    GrowthMeasurement::create([
                        'student_id' => $student->id,
                        'measured_at' => $dateString,
                        'weight_kg' => $values['weight_kg'],
                        'height_cm' => $height,
                        'bmi' => $values['bmi'],
                        'bmi_flag' => $values['bmi_flag'],
                        'assessment_phase' => 'Additional Monitoring',
                        'source_nutrition_status' => $values['bmi_flag'],
                        'assessment_method' => 'NutriFlow BMI-for-age prototype',
                        'data_origin' => 'Synthetic',
                    ]);

                    $existingDates[$dateString] = true;
                    $createdForChild++;
                    $createdRecords++;
                    $interval++;
                }

                if ($createdForChild > 0) {
                    $backfilledChildren++;
                }
            }
        });

        $message = "Added {$createdRecords} earlier growth records for {$backfilledChildren} synthetic children.";
        if ($skippedChildren > 0) {
            $message .= " {$skippedChildren} children could not be safely backfilled.";
        }

        $this->command?->info($message);
    }
}
