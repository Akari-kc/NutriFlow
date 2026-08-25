<?php

namespace Tests\Feature;

use App\Models\GrowthMeasurement;
use App\Models\School;
use App\Models\Student;
use App\Support\ChildBmiClassifier;
use Database\Seeders\RebalanceSyntheticNutritionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyntheticNutritionDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebalance_changes_only_measurement_weight_bmi_and_status_with_exact_distribution(): void
    {
        $school = School::create(['name' => 'Eulogio Rodriguez Integrated School']);

        foreach (range(1, 20) as $number) {
            $student = Student::create([
                'name' => 'Synthetic Child '.$number,
                'gender' => $number % 2 === 0 ? 'Female' : 'Male',
                'birthdate' => now('Asia/Manila')->subYears(5 + ($number % 8))->toDateString(),
                'class_name' => 'Grade '.max(1, $number % 7),
                'section' => 'Section A',
                'school_id' => $school->id,
                'allergies' => $number % 4 === 0 ? 'Milk' : null,
            ]);

            foreach ([30, 0] as $daysAgo) {
                $height = 112 + $number;
                GrowthMeasurement::create([
                    'student_id' => $student->id,
                    'measured_at' => now('Asia/Manila')->subDays($daysAgo)->toDateString(),
                    'weight_kg' => round(22 * (($height / 100) ** 2), 2),
                    'height_cm' => $height,
                    'bmi' => 22,
                    'bmi_flag' => ChildBmiClassifier::OVERWEIGHT,
                ]);
            }
        }

        $studentSnapshot = Student::query()->orderBy('id')->get()->toArray();
        $unchangedMeasurementSnapshot = DB::table('growth_measurements')
            ->orderBy('id')
            ->get(['id', 'student_id', 'measured_at', 'height_cm'])
            ->map(fn ($row) => (array) $row)
            ->all();

        (new RebalanceSyntheticNutritionSeeder)->run();

        $students = Student::with('latestMeasurement')->orderBy('id')->get();
        $distribution = $students
            ->map(fn (Student $student) => ChildBmiClassifier::classifyForStudent($student, $student->latestMeasurement))
            ->countBy();

        $this->assertSame(14, $distribution[ChildBmiClassifier::UNDERNOURISHED]);
        $this->assertSame(4, $distribution[ChildBmiClassifier::NORMAL]);
        $this->assertSame(2, $distribution[ChildBmiClassifier::SEVERELY_UNDERNOURISHED]);
        $this->assertFalse($distribution->has(ChildBmiClassifier::OVERWEIGHT));
        $this->assertFalse($distribution->has(ChildBmiClassifier::OBESE));
        $this->assertSame($studentSnapshot, Student::query()->orderBy('id')->get()->toArray());
        $this->assertSame(
            $unchangedMeasurementSnapshot,
            DB::table('growth_measurements')
                ->orderBy('id')
                ->get(['id', 'student_id', 'measured_at', 'height_cm'])
                ->map(fn ($row) => (array) $row)
                ->all()
        );

        GrowthMeasurement::with('student')->get()->each(function (GrowthMeasurement $measurement) {
            $calculatedBmi = (float) $measurement->weight_kg / (((float) $measurement->height_cm / 100) ** 2);
            $canonicalStatus = ChildBmiClassifier::classifyForStudent($measurement->student, $measurement);

            $this->assertEqualsWithDelta((float) $measurement->bmi, $calculatedBmi, 0.02);
            $this->assertSame($canonicalStatus, $measurement->bmi_flag);
        });
    }
}
