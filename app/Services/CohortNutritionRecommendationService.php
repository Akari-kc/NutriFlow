<?php

namespace App\Services;

use App\Contracts\NutritionRiskPredictor;
use App\Models\Food;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CohortNutritionRecommendationService
{
    public function __construct(
        private readonly NutritionFeatureBuilder $featureBuilder,
        private readonly NutritionRiskPredictor $predictor,
    ) {}

    /**
     * @param  Collection<int, Student>  $students
     * @return array<string, mixed>
     */
    public function recommend(Collection $students): array
    {
        if (! config('nutriflow_ml.enabled')) {
            return [
                'status' => 'disabled',
                'message' => 'Prototype group recommendations are currently disabled. Manual meal planning remains available.',
            ];
        }

        $preparedMembers = collect();
        $excluded = collect();

        foreach ($students as $student) {
            $prepared = $this->featureBuilder->build($student);
            if (($prepared['status'] ?? null) !== 'ready') {
                $excluded->push((string) ($prepared['status'] ?? 'unavailable'));

                continue;
            }

            $preparedMembers->push([
                'student' => $student,
                'features' => $prepared['features'],
                'input_summary' => $prepared['input_summary'],
            ]);
        }

        if ($preparedMembers->isEmpty()) {
            return [
                'status' => 'unavailable',
                'message' => 'None of the selected children have two usable in-scope growth records.',
                'summary' => $this->summary($students, collect(), $excluded, 0),
                'priorities' => [],
                'meals' => [],
                'checks' => $this->constraintChecks(),
            ];
        }

        try {
            $predictions = $this->predictor->predictMany($preparedMembers->pluck('features')->all());
            $members = $preparedMembers->values()->map(function (array $member, int $index) use ($predictions) {
                $prediction = $predictions[$index];
                $probability = (float) $prediction['future_undernutrition_probability'];

                return [
                    ...$member,
                    'probability' => $probability,
                    'risk_category' => $probability >= (float) config('nutriflow_ml.risk_threshold', .5)
                        ? 'higher'
                        : 'lower',
                ];
            });

            $ranking = $this->rankFoods($members);
        } catch (Throwable $exception) {
            Log::warning('NutriFlow cohort recommendation failed.', [
                'school_id' => $students->first()?->school_id,
                'selected_count' => $students->count(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'status' => 'service_error',
                'message' => 'Group recommendations are temporarily unavailable. Manual meal planning remains available.',
            ];
        }

        return [
            'status' => 'success',
            'model_version' => (string) ($predictions[0]['model_version'] ?? 'prototype-v2'),
            'training_data_type' => 'synthetic',
            'clinical_validation' => false,
            'generated_at' => now('Asia/Manila')->toIso8601String(),
            'summary' => $this->summary(
                $students,
                $members,
                $excluded,
                (int) $ranking['allergy_conflicting_foods_excluded']
            ),
            'priorities' => $this->cohortPriorities($members),
            'meals' => $ranking['meals'],
            'checks' => $this->constraintChecks(),
            'notice' => 'Suggestions combine the eligible children’s nutrient needs and give higher-risk children more influence. Recorded allergy conflicts are excluded. Cost, official budget, inventory, and preparation feasibility cannot be evaluated with the current data.',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $members
     * @return array{meals: array<int, array<string, mixed>>, allergy_conflicting_foods_excluded: int}
     */
    private function rankFoods(Collection $members): array
    {
        $schoolId = $members->first()['student']->school_id ?? null;
        $foods = Food::query()
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->orderBy('name')
            ->get();

        $scoringMembers = $members->map(function (array $member) {
            $target = $this->findPdriTarget(
                (float) ($member['input_summary']['age_months'] ?? 0) / 12,
                (string) $member['student']->gender
            );
            if (! $target) {
                throw new RuntimeException('A selected child has no matching PDRI reference target.');
            }

            $member['targets'] = [
                'kcal' => (float) $target['one_third_energy_kcal'],
                'protein_g' => (float) $target['one_third_protein_g'],
                'iron_mg' => (float) $target['one_third_iron_mg'],
                'vit_c_mg' => (float) $target['one_third_vitamin_c_mg'],
                'calcium_mg' => (float) $target['one_third_calcium_mg'],
            ];
            $member['weights'] = $this->nutrientWeights($member['risk_category'] === 'higher');
            $member['priority_weight'] = 1 + (float) $member['probability'];

            return $member;
        });

        $allergyExcluded = 0;
        $ranked = $foods->map(function (Food $food) use ($scoringMembers, &$allergyExcluded) {
            if ($scoringMembers->contains(fn (array $member) => $this->hasRecordedAllergyConflict($member['student'], $food))) {
                $allergyExcluded++;

                return null;
            }

            $weightedScore = 0.0;
            $totalPriorityWeight = 0.0;
            $weightedCoverage = [];
            $supportedChildren = 0;
            $supportedHigherRisk = 0;
            $higherRiskChildren = 0;

            foreach ($scoringMembers as $member) {
                $childScore = 0.0;
                $childCoverage = [];
                foreach ($member['weights'] as $nutrient => $nutrientWeight) {
                    $target = max((float) $member['targets'][$nutrient], .0001);
                    $ratio = max(0, (float) $food->{$nutrient}) / $target;
                    $childCoverage[$nutrient] = $ratio;
                    $childScore += $nutrientWeight * min($ratio, 1);
                }

                $priorityWeight = (float) $member['priority_weight'];
                $weightedScore += $childScore * $priorityWeight;
                $totalPriorityWeight += $priorityWeight;

                foreach ($childCoverage as $nutrient => $ratio) {
                    $weightedCoverage[$nutrient] = ($weightedCoverage[$nutrient] ?? 0) + ($ratio * $priorityWeight);
                }

                if ($childScore >= .6) {
                    $supportedChildren++;
                    if ($member['risk_category'] === 'higher') {
                        $supportedHigherRisk++;
                    }
                }
                if ($member['risk_category'] === 'higher') {
                    $higherRiskChildren++;
                }
            }

            $score = $weightedScore / max($totalPriorityWeight, .0001);
            $coverage = collect($weightedCoverage)
                ->map(fn ($value) => round(($value / max($totalPriorityWeight, .0001)) * 100, 1))
                ->sortDesc();

            return [
                'food_id' => $food->id,
                'name' => $food->name,
                'portion' => $food->portion ?: 'Recorded serving',
                'nutrition_score' => round($score, 4),
                'match_label' => $this->matchLabel($score),
                'support_count' => $supportedChildren,
                'support_percent' => round(($supportedChildren / max($scoringMembers->count(), 1)) * 100),
                'higher_risk_support_count' => $supportedHigherRisk,
                'higher_risk_count' => $higherRiskChildren,
                'key_nutrients' => $coverage->keys()->take(3)->map(fn ($item) => $this->nutrientLabel($item))->values()->all(),
                'coverage' => $coverage->mapWithKeys(fn ($value, $key) => [$this->nutrientLabel($key) => $value])->all(),
                'allergy_status' => 'No conflict found in recorded allergy and food text',
            ];
        })->filter()->sortByDesc('nutrition_score')->take(5)->values()->all();

        return [
            'meals' => $ranked,
            'allergy_conflicting_foods_excluded' => $allergyExcluded,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $members
     * @return array<string, string>
     */
    private function cohortPriorities(Collection $members): array
    {
        $scores = [];
        $totalWeight = 0.0;

        foreach ($members as $member) {
            $priorityWeight = 1 + (float) $member['probability'];
            $totalWeight += $priorityWeight;
            foreach ($this->nutrientWeights($member['risk_category'] === 'higher') as $nutrient => $weight) {
                $scores[$nutrient] = ($scores[$nutrient] ?? 0) + ($weight * $priorityWeight);
            }
        }

        arsort($scores);
        $priorities = [];
        foreach (array_keys($scores) as $index => $nutrient) {
            $priorities[$this->nutrientLabel($nutrient)] = $index < 2 ? 'High' : 'Moderate';
        }

        return $priorities;
    }

    /**
     * @param  Collection<int, Student>  $selected
     * @param  Collection<int, array<string, mixed>>  $members
     * @param  Collection<int, string>  $excluded
     * @return array<string, mixed>
     */
    private function summary(Collection $selected, Collection $members, Collection $excluded, int $allergyExcluded): array
    {
        $excludedCounts = $excluded->countBy()->mapWithKeys(fn ($count, $status) => [
            match ($status) {
                'insufficient_data' => 'Insufficient data',
                'out_of_scope' => 'Outside prototype scope',
                default => 'Unavailable',
            } => $count,
        ])->all();

        return [
            'selected_count' => $selected->count(),
            'eligible_count' => $members->count(),
            'higher_risk_count' => $members->where('risk_category', 'higher')->count(),
            'lower_risk_count' => $members->where('risk_category', 'lower')->count(),
            'not_assessed_count' => $excluded->count(),
            'not_assessed_reasons' => $excludedCounts,
            'recorded_allergy_count' => $selected->filter(fn (Student $student) => $this->splitAllergies($student->allergies)->isNotEmpty())->count(),
            'allergy_conflicting_foods_excluded' => $allergyExcluded,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function nutrientWeights(bool $higherRisk): array
    {
        return $higherRisk
            ? ['kcal' => .25, 'protein_g' => .30, 'iron_mg' => .20, 'vit_c_mg' => .10, 'calcium_mg' => .15]
            : ['kcal' => .25, 'protein_g' => .25, 'iron_mg' => .15, 'vit_c_mg' => .15, 'calcium_mg' => .20];
    }

    /**
     * @return array<string, string>
     */
    private function constraintChecks(): array
    {
        return [
            'Nutrition suitability' => 'Evaluated for eligible children',
            'Recorded allergy conflicts' => 'Excluded using available text',
            'Cost per serving' => 'Not available',
            'Budget feasibility' => 'Not available',
            'Inventory availability' => 'Not available',
            'Preparation feasibility' => 'Not available',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function findPdriTarget(float $ageYears, string $gender): ?array
    {
        $path = (string) config('nutriflow_ml.pdri_path');
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('The PDRI reference file is unavailable.');
        }

        $ageGroup = match (true) {
            $ageYears <= 2 => '1-2',
            $ageYears <= 5 => '3-5',
            $ageYears <= 9 => '6-9',
            $ageYears <= 12 => '10-12',
            default => '13-15',
        };
        $sex = strtolower(trim($gender)) === 'male' ? 'M' : 'F';
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return null;
        }

        $headers = fgetcsv($handle, escape: '');
        $headers[0] = ltrim((string) ($headers[0] ?? ''), "\xEF\xBB\xBF");
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if (count($headers) !== count($values)) {
                continue;
            }
            $row = array_combine($headers, $values);
            if ($row['age_group_years'] === $ageGroup && $row['sex'] === $sex) {
                fclose($handle);

                return $row;
            }
        }
        fclose($handle);

        return null;
    }

    private function hasRecordedAllergyConflict(Student $student, Food $food): bool
    {
        $allergies = $this->splitAllergies($student->allergies);
        if ($allergies->isEmpty()) {
            return false;
        }

        $foodText = strtolower(implode(' ', [
            (string) $food->name,
            (string) $food->portion,
            (string) $food->recipe,
            ...$food->allergyAlerts(),
        ]));

        return $allergies->contains(function (string $allergy) use ($foodText) {
            return $this->allergySearchTerms($allergy)
                ->contains(fn (string $term) => $term !== '' && str_contains($foodText, $term));
        });
    }

    /**
     * @return Collection<int, string>
     */
    private function splitAllergies(?string $allergies): Collection
    {
        return collect(preg_split('/[,;\n]+/', (string) $allergies))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function allergySearchTerms(string $allergy): Collection
    {
        $base = strtolower(trim($allergy));
        $terms = collect([$base]);
        $aliases = [
            'milk' => ['milk', 'dairy', 'lactose', 'cheese', 'cream', 'butter'],
            'lactose' => ['milk', 'dairy', 'lactose'],
            'egg' => ['egg', 'eggs'],
            'eggs' => ['egg', 'eggs'],
            'peanuts' => ['peanut', 'peanuts', 'nut'],
            'tree nuts' => ['tree nuts', 'nut', 'almond', 'cashew', 'walnut'],
            'wheat/gluten' => ['wheat', 'gluten', 'flour', 'bread', 'noodle', 'pancit'],
            'shellfish' => ['shellfish', 'shrimp', 'crab', 'squid'],
        ];

        foreach ($aliases as $needle => $expandedTerms) {
            if ($base === $needle || str_contains($base, $needle)) {
                $terms = $terms->merge($expandedTerms);
            }
        }

        return $terms->map(fn ($term) => strtolower(trim($term)))->filter()->unique()->values();
    }

    private function matchLabel(float $score): string
    {
        return match (true) {
            $score >= .80 => 'Strong Group Match',
            $score >= .60 => 'Good Group Match',
            default => 'Moderate Group Match',
        };
    }

    private function nutrientLabel(string $nutrient): string
    {
        return match ($nutrient) {
            'kcal' => 'Energy',
            'protein_g' => 'Protein',
            'iron_mg' => 'Iron',
            'vit_c_mg' => 'Vitamin C',
            'calcium_mg' => 'Calcium',
            default => $nutrient,
        };
    }
}
