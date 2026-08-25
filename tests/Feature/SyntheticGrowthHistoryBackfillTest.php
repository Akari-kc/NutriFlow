<?php

namespace Tests\Feature;

use App\Models\GrowthMeasurement;
use App\Models\School;
use App\Models\Student;
use App\Support\ChildBmiClassifier;
use App\Support\SyntheticGrowthProfile;
use Database\Seeders\BackfillSyntheticGrowthHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyntheticGrowthHistoryBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_only_earlier_records_until_each_child_has_five_distinct_dates(): void
    {
        $school = School::create(['name' => 'Eulogio Rodriguez Integrated School']);
        $childNeedingHistory = $this->createStudent($school, 'Child Needing History');
        $anchorDate = now('Asia/Manila')->startOfDay();

        GrowthMeasurement::create([
            'student_id' => $childNeedingHistory->id,
            'measured_at' => $anchorDate->toDateString(),
            'weight_kg' => 18.74,
            'height_cm' => 121,
            'bmi' => 12.8,
            'bmi_flag' => ChildBmiClassifier::UNDERNOURISHED,
        ]);
        GrowthMeasurement::create([
            'student_id' => $childNeedingHistory->id,
            'measured_at' => $anchorDate->toDateString(),
            'weight_kg' => 18.43,
            'height_cm' => 120,
            'bmi' => 12.8,
            'bmi_flag' => ChildBmiClassifier::UNDERNOURISHED,
        ]);

        $childWithHistory = $this->createStudent($school, 'Child With History');
        foreach ([120, 90, 60, 30, 0] as $daysAgo) {
            $measuredAt = $anchorDate->copy()->subDays($daysAgo);
            $height = 118 + ((120 - $daysAgo) / 30 * 0.4);
            $values = SyntheticGrowthProfile::measurementValues(
                $childWithHistory,
                $measuredAt,
                $height,
                ChildBmiClassifier::UNDERNOURISHED
            );

            GrowthMeasurement::create([
                'student_id' => $childWithHistory->id,
                'measured_at' => $measuredAt->toDateString(),
                'weight_kg' => $values['weight_kg'],
                'height_cm' => $height,
                'bmi' => $values['bmi'],
                'bmi_flag' => $values['bmi_flag'],
            ]);
        }

        $studentSnapshot = Student::query()->orderBy('id')->get()->toArray();
        $existingMeasurementIds = GrowthMeasurement::query()->pluck('id');
        $measurementSnapshot = $this->measurementSnapshot($existingMeasurementIds->all());

        (new BackfillSyntheticGrowthHistorySeeder)->run();

        $this->assertSame($studentSnapshot, Student::query()->orderBy('id')->get()->toArray());
        $this->assertSame($measurementSnapshot, $this->measurementSnapshot($existingMeasurementIds->all()));
        $this->assertSame(6, $childNeedingHistory->measurements()->count());
        $this->assertSame(5, $childNeedingHistory->measurements()->distinct()->count('measured_at'));
        $this->assertSame(5, $childWithHistory->measurements()->count());

        $newRecords = $childNeedingHistory->measurements()
            ->whereNotIn('id', $existingMeasurementIds)
            ->orderBy('measured_at')
            ->get();

        $this->assertCount(4, $newRecords);
        $this->assertTrue($newRecords->every(
            fn (GrowthMeasurement $measurement) => $measurement->measured_at->lt($anchorDate)
        ));

        $chronologicalHeights = $newRecords->pluck('height_cm')->map(fn ($height) => (float) $height)->all();
        $this->assertSame($chronologicalHeights, collect($chronologicalHeights)->sort()->values()->all());
        $this->assertLessThan(120, max($chronologicalHeights));

        $newRecords->each(function (GrowthMeasurement $measurement) use ($childNeedingHistory) {
            $calculatedBmi = (float) $measurement->weight_kg / (((float) $measurement->height_cm / 100) ** 2);

            $this->assertEqualsWithDelta((float) $measurement->bmi, $calculatedBmi, 0.02);
            $this->assertSame(
                ChildBmiClassifier::classifyForStudent($childNeedingHistory, $measurement),
                $measurement->bmi_flag
            );
        });
    }

    private function createStudent(School $school, string $name): Student
    {
        return Student::create([
            'name' => $name,
            'gender' => 'Male',
            'birthdate' => now('Asia/Manila')->subYears(8)->toDateString(),
            'class_name' => 'Grade 3',
            'section' => 'Earth',
            'school_id' => $school->id,
        ]);
    }

    /**
     * @param  array<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function measurementSnapshot(array $ids): array
    {
        return DB::table('growth_measurements')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
