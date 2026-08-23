<?php

namespace App\Services;

use App\Models\Food;
use App\Models\Student;
use RuntimeException;

class NutritionRecommendationService
{
    /**
     * @return array<string, mixed>
     */
    public function rank(Student $student, float $probability, array $inputSummary): array
    {
        $target = $this->findPdriTarget(
            (float) ($inputSummary['age_months'] ?? 0) / 12,
            (string) $student->gender
        );

        if (! $target) {
            return [
                'status' => 'unavailable',
                'message' => 'No matching PDRI reference target is available.',
                'meals' => [],
                'checks' => $this->constraintChecks(),
            ];
        }

        $elevated = $probability >= (float) config('nutriflow_ml.risk_threshold', 0.5);
        $weights = $elevated
            ? ['kcal' => .25, 'protein_g' => .30, 'iron_mg' => .20, 'vit_c_mg' => .10, 'calcium_mg' => .15]
            : ['kcal' => .25, 'protein_g' => .25, 'iron_mg' => .15, 'vit_c_mg' => .15, 'calcium_mg' => .20];
        $targets = [
            'kcal' => (float) $target['one_third_energy_kcal'],
            'protein_g' => (float) $target['one_third_protein_g'],
            'iron_mg' => (float) $target['one_third_iron_mg'],
            'vit_c_mg' => (float) $target['one_third_vitamin_c_mg'],
            'calcium_mg' => (float) $target['one_third_calcium_mg'],
        ];

        $foods = Food::query()
            ->when($student->school_id, fn ($query) => $query->where('school_id', $student->school_id))
            ->orderBy('name')
            ->get();

        $ranked = $foods->map(function (Food $food) use ($weights, $targets) {
            $score = 0.0;
            $coverage = [];
            foreach ($weights as $nutrient => $weight) {
                $target = max($targets[$nutrient], 0.0001);
                $ratio = max(0, (float) $food->{$nutrient}) / $target;
                $coverage[$nutrient] = round($ratio * 100, 1);
                $score += $weight * min($ratio, 1);
            }
            arsort($coverage);

            return [
                'food_id' => $food->id,
                'name' => $food->name,
                'portion' => $food->portion ?: 'Recorded serving',
                'nutrition_score' => round($score, 4),
                'match_label' => $this->matchLabel($score),
                'key_nutrients' => collect(array_keys($coverage))->take(3)->map(fn ($item) => $this->nutrientLabel($item))->values()->all(),
                'coverage' => collect($coverage)->mapWithKeys(fn ($value, $key) => [$this->nutrientLabel($key) => $value])->all(),
            ];
        })->sortByDesc('nutrition_score')->take(5)->values()->all();

        return [
            'status' => $ranked ? 'provisional' : 'unavailable',
            'label' => 'Provisional Nutrition Ranking',
            'reference' => 'One-third PDRI prototype target',
            'pdri_group' => $target['age_group_years'].' / '.$target['sex'],
            'meals' => $ranked,
            'checks' => $this->constraintChecks(),
            'notice' => 'Individual nutrition guidance only. School meal budget and operational feasibility are handled during group meal-session planning.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function constraintChecks(): array
    {
        return [
            'Nutrition suitability' => 'Evaluated',
            'Recorded allergy safety' => 'Not evaluated for this prototype view',
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

    private function matchLabel(float $score): string
    {
        return match (true) {
            $score >= .80 => 'Strong Nutrition Match',
            $score >= .60 => 'Good Nutrition Match',
            default => 'Moderate Nutrition Match',
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
