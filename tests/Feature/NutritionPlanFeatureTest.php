<?php

namespace Tests\Feature;

use App\Contracts\NutritionRiskPredictor;
use App\Models\Food;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\NutritionAssessmentService;
use App\Services\NutritionFeatureBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NutritionPlanFeatureTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $aide;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Nutrition Plan Test School']);
        $this->admin = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => User::ROLE_SCHOOL_ADMIN,
        ]);
        $this->aide = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => User::ROLE_NUTRITION_AIDE,
        ]);
    }

    public function test_feature_builder_uses_latest_distinct_dates_and_canonical_status(): void
    {
        $student = $this->studentWithHistory();
        $priorDate = now()->subDays(30)->toDateString();

        $student->measurements()->create([
            'measured_at' => $priorDate,
            'weight_kg' => 22.65,
            'height_cm' => 130,
            'bmi' => 13.4,
            'bmi_flag' => 'Underweight',
        ]);
        $student->measurements()->create([
            'measured_at' => now()->addDay()->toDateString(),
            'weight_kg' => 45,
            'height_cm' => 130,
            'bmi' => 26.63,
            'bmi_flag' => 'Obese',
        ]);

        $result = app(NutritionFeatureBuilder::class)->build($student);

        $this->assertSame('ready', $result['status']);
        $this->assertSame(13.4, $result['features']['prior_bmi']);
        $this->assertSame(30, $result['features']['days_since_prior']);
        $this->assertSame('Normal', $result['features']['current_bmi_flag']);
        $this->assertSame(now()->toDateString(), $result['input_summary']['current_measurement_date']);
    }

    public function test_feature_builder_returns_explicit_insufficient_and_out_of_scope_states(): void
    {
        $insufficient = Student::create([
            'name' => 'Insufficient Child',
            'gender' => 'Male',
            'birthdate' => now()->subYears(9)->toDateString(),
            'school_id' => $this->school->id,
        ]);
        $insufficient->measurements()->create([
            'measured_at' => now()->toDateString(),
            'weight_kg' => 24,
            'height_cm' => 130,
            'bmi' => 14.2,
            'bmi_flag' => 'Normal',
        ]);

        $this->assertSame(
            'insufficient_data',
            app(NutritionFeatureBuilder::class)->build($insufficient)['status']
        );

        $outside = Student::create([
            'name' => 'Outside Scope Child',
            'gender' => 'Male',
            'birthdate' => now()->subYears(9)->toDateString(),
            'school_id' => $this->school->id,
        ]);
        $outside->measurements()->createMany([
            [
                'measured_at' => now()->subDays(30)->toDateString(),
                'weight_kg' => 31,
                'height_cm' => 130,
                'bmi' => 18.34,
                'bmi_flag' => 'Normal',
            ],
            [
                'measured_at' => now()->toDateString(),
                'weight_kg' => 32.1,
                'height_cm' => 130,
                'bmi' => 19,
                'bmi_flag' => 'Normal',
            ],
        ]);

        $outsideResult = app(NutritionFeatureBuilder::class)->build($outside);
        $this->assertSame('out_of_scope', $outsideResult['status']);
        $this->assertSame('Overweight', $outsideResult['current_status']);
    }

    public function test_assessment_keeps_prediction_and_recommendation_layers_separate(): void
    {
        $student = $this->studentWithHistory();
        $food = new Food([
            'name' => 'Monggo Meal',
            'portion' => '1 serving',
            'school_id' => $this->school->id,
        ]);
        $food->forceFill([
            'kcal' => 420,
            'protein_g' => 18,
            'iron_mg' => 4,
            'vit_c_mg' => 18,
            'calcium_mg' => 180,
        ])->save();

        $this->app->bind(NutritionRiskPredictor::class, fn () => new class implements NutritionRiskPredictor
        {
            public function predict(array $features): array
            {
                return [
                    'model_version' => 'prototype-v2',
                    'future_undernutrition_probability' => .82,
                    'predicted_class' => 1,
                    'threshold' => .5,
                    'horizon_days' => ['minimum' => 21, 'maximum' => 40],
                ];
            }

            public function predictMany(array $featureRows): array
            {
                return array_map(fn ($features) => $this->predict($features), $featureRows);
            }
        });

        $result = app(NutritionAssessmentService::class)->assess($student);

        $this->assertSame('success', $result['status']);
        $this->assertSame('Higher Risk', $result['risk_label']);
        $this->assertSame(82.0, $result['risk_percent']);
        $this->assertSame('provisional', $result['recommendation']['status']);
        $this->assertSame('Evaluated', $result['recommendation']['checks']['Nutrition suitability']);
        $this->assertSame('Not evaluated for this prototype view', $result['recommendation']['checks']['Recorded allergy safety']);
        $this->assertSame('Monggo Meal', $result['recommendation']['meals'][0]['name']);
    }

    public function test_admin_and_aide_can_generate_a_scoped_prototype_assessment(): void
    {
        $student = $this->studentWithHistory();
        $assessment = $this->sampleAssessment();
        $mock = $this->mock(NutritionAssessmentService::class);
        $mock->shouldReceive('assess')->twice()->withArgs(fn ($value) => $value->is($student))->andReturn($assessment);

        foreach ([$this->admin, $this->aide] as $user) {
            $response = $this->actingAs($user)->post(route('students.nutrition-assessment', $student));
            $response->assertRedirect(route('students.show', $student).'#nutritionPlan');
            $response->assertSessionHas('nutritionAssessment.status', 'success');
        }
    }

    public function test_assessment_route_rejects_guests_and_other_school_users(): void
    {
        $student = $this->studentWithHistory();
        $otherSchool = School::create(['name' => 'Other Nutrition School']);
        $otherUser = User::factory()->create([
            'school_id' => $otherSchool->id,
            'role' => User::ROLE_NUTRITION_AIDE,
        ]);

        $this->post(route('students.nutrition-assessment', $student))->assertRedirect('/login');
        $this->actingAs($otherUser)
            ->post(route('students.nutrition-assessment', $student))
            ->assertForbidden();
    }

    public function test_child_profile_uses_plain_language_individual_guidance_without_budget_planning(): void
    {
        $student = $this->studentWithHistory();

        $this->actingAs($this->aide)
            ->withSession(['nutritionAssessment' => $this->sampleAssessment()])
            ->get(route('students.show', $student).'#nutritionPlan')
            ->assertOk()
            ->assertSee('Nutrition Plan')
            ->assertSee('Prototype Simulation')
            ->assertSee('Higher Risk')
            ->assertSee('Current Status')
            ->assertSee('Individual Guidance Checks')
            ->assertSee('Food Guidance for This Child')
            ->assertSee('School budget is not part of this individual view')
            ->assertDontSee('Meal Planning Budget')
            ->assertDontSee('Budget filtering is not applied')
            ->assertSee('It is not a medical diagnosis')
            ->assertDontSee('Gradient Boosting');
    }

    private function studentWithHistory(): Student
    {
        $student = Student::create([
            'name' => 'Prototype Child',
            'gender' => 'Male',
            'birthdate' => now()->subYears(9)->toDateString(),
            'school_id' => $this->school->id,
        ]);
        $student->measurements()->createMany([
            [
                'measured_at' => now()->subDays(30)->toDateString(),
                'weight_kg' => 22.31,
                'height_cm' => 130,
                'bmi' => 13.2,
                'bmi_flag' => 'Underweight',
            ],
            [
                'measured_at' => now()->toDateString(),
                'weight_kg' => 24,
                'height_cm' => 130,
                'bmi' => 14.2,
                'bmi_flag' => 'Normal',
            ],
        ]);

        return $student;
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleAssessment(): array
    {
        return [
            'status' => 'success',
            'model_version' => 'prototype-v2',
            'prediction_horizon_days' => ['minimum' => 21, 'maximum' => 40],
            'risk_probability' => .82,
            'risk_percent' => 82,
            'risk_category' => 'higher',
            'risk_label' => 'Higher Risk',
            'threshold' => .5,
            'training_data_type' => 'synthetic',
            'clinical_validation' => false,
            'input_summary' => [
                'current_status' => 'Normal',
                'current_weight_kg' => 24,
                'previous_weight_kg' => 22.31,
                'weight_change_kg' => 1.69,
                'current_bmi' => 14.2,
                'previous_bmi' => 13.2,
                'bmi_change' => 1,
                'previous_measurement_date' => now()->subDays(30)->toDateString(),
                'current_measurement_date' => now()->toDateString(),
            ],
            'nutrition_priority' => ['Energy' => 'High', 'Protein' => 'High'],
            'recommendation' => [
                'status' => 'provisional',
                'label' => 'Provisional Nutrition Ranking',
                'meals' => [[
                    'name' => 'Monggo Meal',
                    'portion' => '1 serving',
                    'nutrition_score' => .82,
                    'match_label' => 'Strong Nutrition Match',
                    'key_nutrients' => ['Protein', 'Iron', 'Energy'],
                    'coverage' => ['Protein' => 100, 'Iron' => 90, 'Energy' => 84],
                ]],
                'checks' => [
                    'Nutrition suitability' => 'Evaluated',
                    'Recorded allergy safety' => 'Not evaluated for this prototype view',
                ],
            ],
        ];
    }
}
