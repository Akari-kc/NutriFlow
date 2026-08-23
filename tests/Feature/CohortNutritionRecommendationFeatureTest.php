<?php

namespace Tests\Feature;

use App\Contracts\NutritionRiskPredictor;
use App\Models\Food;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CohortNutritionRecommendationFeatureTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $aide;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Cohort Recommendation School']);
        $this->aide = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => User::ROLE_NUTRITION_AIDE,
        ]);
    }

    public function test_add_session_shows_group_recommendations_budget_context_and_manual_menu(): void
    {
        $this->studentWithHistory('Schedule Child');

        $this->actingAs($this->aide)
            ->get(route('feeding-schedules.index'))
            ->assertOk()
            ->assertSee('Prototype Group Meal Suggestions')
            ->assertSee('Select all')
            ->assertSee('Clear selection')
            ->assertSee('Generate Group Suggestions')
            ->assertSee('Combined Nutrient Priorities')
            ->assertSee('School Budget Scenario')
            ->assertSee('cannot filter suggestions until verified food prices are available')
            ->assertSee('Menu Items')
            ->assertSee('Nothing is saved automatically');
    }

    public function test_group_endpoint_aggregates_eligible_children_and_excludes_recorded_allergy_conflicts(): void
    {
        $first = $this->studentWithHistory('Higher Risk Child', 'Milk');
        $second = $this->studentWithHistory('Lower Risk Child');

        $safeFood = new Food([
            'name' => 'Protein Vegetable Bowl',
            'portion' => '1 bowl',
            'school_id' => $this->school->id,
            'recipe' => 'beans vegetables rice',
        ]);
        $safeFood->forceFill([
            'kcal' => 420,
            'protein_g' => 24,
            'iron_mg' => 5,
            'vit_c_mg' => 28,
            'calcium_mg' => 220,
        ])->save();

        $allergyFood = new Food([
            'name' => 'Fortified Milk',
            'portion' => '1 cup',
            'school_id' => $this->school->id,
            'recipe' => 'milk powder',
        ]);
        $allergyFood->forceFill([
            'kcal' => 500,
            'protein_g' => 30,
            'iron_mg' => 8,
            'vit_c_mg' => 35,
            'calcium_mg' => 500,
        ])->save();

        $this->app->bind(NutritionRiskPredictor::class, fn () => new class implements NutritionRiskPredictor
        {
            public function predict(array $features): array
            {
                return $this->result(.85);
            }

            public function predictMany(array $featureRows): array
            {
                $probabilities = [.85, .25];

                return array_map(
                    fn ($features, $index) => $this->result($probabilities[$index] ?? .25),
                    $featureRows,
                    array_keys($featureRows)
                );
            }

            private function result(float $probability): array
            {
                return [
                    'model_version' => 'prototype-v2',
                    'future_undernutrition_probability' => $probability,
                    'predicted_class' => (int) ($probability >= .5),
                    'threshold' => .5,
                    'horizon_days' => ['minimum' => 21, 'maximum' => 40],
                ];
            }
        });

        $response = $this->actingAs($this->aide)->postJson(
            route('feeding-schedules.recommendations'),
            ['participant_student_ids' => [$first->id, $second->id]]
        );

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('summary.selected_count', 2)
            ->assertJsonPath('summary.eligible_count', 2)
            ->assertJsonPath('summary.higher_risk_count', 1)
            ->assertJsonPath('summary.lower_risk_count', 1)
            ->assertJsonPath('summary.recorded_allergy_count', 1)
            ->assertJsonPath('summary.allergy_conflicting_foods_excluded', 1)
            ->assertJsonPath('checks.Nutrition suitability', 'Evaluated for eligible children')
            ->assertJsonPath('checks.Budget feasibility', 'Not available')
            ->assertJsonPath('meals.0.food_id', $safeFood->id);

        $names = collect($response->json('meals'))->pluck('name');
        $this->assertTrue($names->contains('Protein Vegetable Bowl'));
        $this->assertFalse($names->contains('Fortified Milk'));
    }

    public function test_group_endpoint_rejects_guests_and_cross_school_students(): void
    {
        $student = $this->studentWithHistory('Scoped Child');
        $otherSchool = School::create(['name' => 'Other Cohort School']);
        $otherStudent = Student::create([
            'name' => 'Other School Child',
            'gender' => 'Male',
            'birthdate' => now()->subYears(9)->toDateString(),
            'school_id' => $otherSchool->id,
        ]);

        $this->postJson(
            route('feeding-schedules.recommendations'),
            ['participant_student_ids' => [$student->id]]
        )->assertUnauthorized();

        $this->actingAs($this->aide)->postJson(
            route('feeding-schedules.recommendations'),
            ['participant_student_ids' => [$student->id, $otherStudent->id]]
        )->assertForbidden();
    }

    private function studentWithHistory(string $name, ?string $allergies = null): Student
    {
        $student = Student::create([
            'name' => $name,
            'gender' => 'Male',
            'birthdate' => now()->subYears(9)->toDateString(),
            'school_id' => $this->school->id,
            'allergies' => $allergies,
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
}
