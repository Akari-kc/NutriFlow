<?php

namespace Tests\Feature;

use App\Models\FeedingProgramEnrollment;
use App\Models\GrowthMeasurement;
use App\Models\School;
use App\Models\Student;
use App\Support\ChildBmiClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErisLearnerSchemaFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_ids_are_school_scoped_and_names_are_optional_display_metadata(): void
    {
        $firstSchool = School::create(['name' => 'First School']);
        $secondSchool = School::create(['name' => 'Second School']);

        $first = Student::create([
            'name' => null,
            'source_learner_reference' => null,
            'gender' => 'Male',
            'school_id' => $firstSchool->id,
        ]);
        $second = Student::create([
            'name' => null,
            'source_learner_reference' => 'Source learner 2',
            'gender' => 'Female',
            'school_id' => $firstSchool->id,
        ]);
        $otherSchool = Student::create([
            'name' => null,
            'gender' => 'Male',
            'school_id' => $secondSchool->id,
        ]);

        $this->assertSame('LEARNER-001', $first->learner_uid);
        $this->assertSame('LEARNER-002', $second->learner_uid);
        $this->assertSame('LEARNER-001', $otherSchool->learner_uid);
        $this->assertSame('LEARNER-001', $first->display_name);
        $this->assertSame('Source learner 2', $second->display_name);
    }

    public function test_report_classifications_map_to_the_existing_severity_levels(): void
    {
        $this->assertSame('Wasted', ChildBmiClassifier::WASTED);
        $this->assertSame('Severely Wasted', ChildBmiClassifier::SEVERELY_WASTED);
        $this->assertSame('Moderate', ChildBmiClassifier::riskLevel(ChildBmiClassifier::WASTED));
        $this->assertSame('Severe', ChildBmiClassifier::riskLevel(ChildBmiClassifier::SEVERELY_WASTED));
        $this->assertSame('Low', ChildBmiClassifier::riskLevel(ChildBmiClassifier::NORMAL));
    }

    public function test_imported_source_classification_is_retained_when_bmi_is_unavailable(): void
    {
        $school = School::create(['name' => 'Source Classification School']);
        $student = Student::create([
            'name' => null,
            'gender' => 'Female',
            'birthdate' => null,
            'school_id' => $school->id,
        ]);
        $measurement = GrowthMeasurement::create([
            'student_id' => $student->id,
            'measured_at' => '2025-07-14',
            'assessment_phase' => 'Baseline',
            'weight_kg' => 15,
            'height_cm' => 105,
            'bmi' => null,
            'bmi_flag' => ChildBmiClassifier::SEVERELY_WASTED,
            'source_nutrition_status' => 'S Wasted',
            'assessment_method' => 'Source report',
            'data_origin' => 'Imported',
        ]);

        $this->assertSame('S Wasted', $measurement->source_nutrition_status);
        $this->assertSame(
            ChildBmiClassifier::SEVERELY_WASTED,
            ChildBmiClassifier::classifyForStudent($student, $measurement)
        );
    }

    public function test_program_administrative_fields_remain_outside_the_student_identity(): void
    {
        $school = School::create(['name' => 'Program School']);
        $student = Student::create([
            'name' => null,
            'gender' => 'Female',
            'school_id' => $school->id,
        ]);

        FeedingProgramEnrollment::create([
            'student_id' => $student->id,
            'school_year' => '2025-2026',
            'milk_consent' => 'Not recorded',
            'four_ps_status' => 'Yes',
            'previous_sbfp_beneficiary' => 'No',
            'data_origin' => 'Imported',
        ]);

        $this->assertSame('LEARNER-001', $student->fresh()->learner_uid);
        $this->assertNull($student->fresh()->name);
        $this->assertSame('Yes', $student->feedingProgramEnrollments()->first()->four_ps_status);
    }
}
