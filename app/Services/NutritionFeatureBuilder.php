<?php

namespace App\Services;

use App\Models\Student;
use App\Support\ChildBmiClassifier;
use Illuminate\Support\Carbon;

class NutritionFeatureBuilder
{
    private const SUPPORTED_STATUSES = [
        ChildBmiClassifier::NORMAL,
        ChildBmiClassifier::UNDERNOURISHED,
        ChildBmiClassifier::SEVERELY_UNDERNOURISHED,
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(Student $student): array
    {
        if (! $student->birthdate || ! $student->gender) {
            return $this->unavailable(
                'insufficient_data',
                'Birthdate and sex are required for this prototype assessment.'
            );
        }

        $measurements = $student->measurements()
            ->whereDate('measured_at', '<=', Carbon::today('Asia/Manila')->toDateString())
            ->whereNotNull('weight_kg')
            ->whereNotNull('height_cm')
            ->whereNotNull('bmi')
            ->where('weight_kg', '>', 0)
            ->where('height_cm', '>', 0)
            ->where('bmi', '>', 0)
            ->orderByDesc('measured_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn ($measurement) => $measurement->measured_at?->toDateString())
            ->take(2)
            ->values();

        if ($measurements->count() < 2) {
            return $this->unavailable(
                'insufficient_data',
                'At least two usable assessments from different dates are required.'
            );
        }

        $current = $measurements[0];
        $prior = $measurements[1];
        $currentDate = Carbon::parse($current->measured_at, 'Asia/Manila')->startOfDay();
        $priorDate = Carbon::parse($prior->measured_at, 'Asia/Manila')->startOfDay();
        $daysSincePrior = (int) round($priorDate->diffInDays($currentDate, false));

        if ($daysSincePrior <= 0) {
            return $this->unavailable(
                'insufficient_data',
                'The two latest usable assessments must have different valid dates.'
            );
        }

        $currentStatus = ChildBmiClassifier::classifyForStudent($student, $current);
        $priorStatus = ChildBmiClassifier::classifyForStudent($student, $prior);

        if (! in_array($currentStatus, self::SUPPORTED_STATUSES, true)) {
            return [
                ...$this->unavailable(
                    'out_of_scope',
                    'The current measured status is outside this undernutrition prototype model.'
                ),
                'current_status' => $currentStatus,
            ];
        }

        $birthdate = Carbon::parse($student->birthdate, 'Asia/Manila')->startOfDay();
        $ageDays = $birthdate->diffInDays($currentDate, false);
        if ($ageDays < 0) {
            return $this->unavailable('insufficient_data', 'The measurement date cannot precede birthdate.');
        }

        $weightChange = (float) $current->weight_kg - (float) $prior->weight_kg;
        $heightChange = (float) $current->height_cm - (float) $prior->height_cm;
        $bmiChange = (float) $current->bmi - (float) $prior->bmi;
        $ageMonths = (float) $ageDays / 30.4375;

        return [
            'status' => 'ready',
            'features' => [
                'age_months_at_anchor' => $ageMonths,
                'current_weight_kg' => (float) $current->weight_kg,
                'current_height_cm' => (float) $current->height_cm,
                'current_bmi' => (float) $current->bmi,
                'prior_weight_kg' => (float) $prior->weight_kg,
                'prior_height_cm' => (float) $prior->height_cm,
                'prior_bmi' => (float) $prior->bmi,
                'days_since_prior' => $daysSincePrior,
                'weight_change_kg' => $weightChange,
                'height_change_cm' => $heightChange,
                'bmi_change' => $bmiChange,
                'weight_change_kg_per_30d' => $weightChange * 30 / $daysSincePrior,
                'bmi_change_per_30d' => $bmiChange * 30 / $daysSincePrior,
                'sex_recorded' => (string) $student->gender,
                'current_bmi_flag' => $currentStatus,
            ],
            'input_summary' => [
                'current_measurement_date' => $currentDate->toDateString(),
                'previous_measurement_date' => $priorDate->toDateString(),
                'days_between_measurements' => $daysSincePrior,
                'age_months' => round($ageMonths, 1),
                'current_weight_kg' => (float) $current->weight_kg,
                'previous_weight_kg' => (float) $prior->weight_kg,
                'weight_change_kg' => round($weightChange, 2),
                'current_height_cm' => (float) $current->height_cm,
                'previous_height_cm' => (float) $prior->height_cm,
                'current_bmi' => (float) $current->bmi,
                'previous_bmi' => (float) $prior->bmi,
                'bmi_change' => round($bmiChange, 2),
                'current_status' => $currentStatus,
                'previous_status' => $priorStatus,
            ],
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function unavailable(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }
}
