<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\Student;
use App\Support\SyntheticGrowthProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RebalanceSyntheticNutritionSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('name', 'Eulogio Rodriguez Integrated School')->first();
        if (! $school) {
            throw new RuntimeException('The synthetic NutriFlow school was not found.');
        }

        $students = Student::query()
            ->where('school_id', $school->id)
            ->whereHas('measurements')
            ->with(['measurements' => fn ($query) => $query->orderBy('measured_at')->orderBy('id')])
            ->orderBy('id')
            ->get();

        DB::transaction(function () use ($students) {
            foreach ($students->values() as $studentIndex => $student) {
                $targetStatus = SyntheticGrowthProfile::statusForOrdinal($studentIndex);
                $measurementCount = $student->measurements->count();

                foreach ($student->measurements->values() as $measurementIndex => $measurement) {
                    $progress = $measurementCount <= 1
                        ? 1.0
                        : $measurementIndex / ($measurementCount - 1);
                    $values = SyntheticGrowthProfile::measurementValues(
                        $student,
                        $measurement->measured_at,
                        (float) $measurement->height_cm,
                        $targetStatus,
                        $progress
                    );

                    $measurement->forceFill(array_merge($values, [
                        'source_nutrition_status' => $values['bmi_flag'],
                        'assessment_method' => 'NutriFlow BMI-for-age prototype',
                        'data_origin' => 'Synthetic',
                    ]))->saveQuietly();
                }
            }
        });

        $this->command?->info(
            'Rebalanced '.$students->count().' synthetic children to 70% wasted, 20% normal, and 10% severely wasted.'
        );
    }
}
