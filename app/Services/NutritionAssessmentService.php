<?php

namespace App\Services;

use App\Contracts\NutritionRiskPredictor;
use App\Models\Student;
use Illuminate\Support\Facades\Log;
use Throwable;

class NutritionAssessmentService
{
    public function __construct(
        private readonly NutritionFeatureBuilder $featureBuilder,
        private readonly NutritionRiskPredictor $predictor,
        private readonly NutritionRecommendationService $recommendations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function assess(Student $student): array
    {
        $prepared = $this->featureBuilder->build($student);
        if (($prepared['status'] ?? null) !== 'ready') {
            return [
                ...$prepared,
                'model_version' => 'prototype-v2',
                'training_data_type' => 'synthetic',
                'clinical_validation' => false,
            ];
        }

        try {
            $prediction = $this->predictor->predict($prepared['features']);
            $probability = (float) $prediction['future_undernutrition_probability'];
            $threshold = (float) ($prediction['threshold'] ?? config('nutriflow_ml.risk_threshold', .5));
            $riskCategory = $probability >= $threshold ? 'higher' : 'lower';
            $priority = $this->nutritionPriority($riskCategory);
            $ranking = $this->recommendations->rank($student, $probability, $prepared['input_summary']);
        } catch (Throwable $exception) {
            Log::warning('NutriFlow prototype assessment failed.', [
                'student_id' => $student->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'status' => 'service_error',
                'message' => 'Predictive assessment is temporarily unavailable. Existing child records were not affected.',
                'model_version' => 'prototype-v2',
                'training_data_type' => 'synthetic',
                'clinical_validation' => false,
            ];
        }

        return [
            'status' => 'success',
            'model_version' => (string) ($prediction['model_version'] ?? 'prototype-v2'),
            'prediction_horizon_days' => $prediction['horizon_days'] ?? ['minimum' => 21, 'maximum' => 40],
            'risk_probability' => $probability,
            'risk_percent' => round($probability * 100),
            'risk_category' => $riskCategory,
            'risk_label' => $riskCategory === 'higher' ? 'Higher Risk' : 'Lower Risk',
            'threshold' => $threshold,
            'training_data_type' => 'synthetic',
            'clinical_validation' => false,
            'generated_at' => now('Asia/Manila')->toIso8601String(),
            'input_summary' => $prepared['input_summary'],
            'nutrition_priority' => $priority,
            'recommendation' => $ranking,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function nutritionPriority(string $riskCategory): array
    {
        return $riskCategory === 'higher'
            ? ['Energy' => 'High', 'Protein' => 'High', 'Iron' => 'Moderate', 'Calcium' => 'Moderate', 'Vitamin C' => 'Moderate']
            : ['Energy' => 'Moderate', 'Protein' => 'Moderate', 'Iron' => 'Moderate', 'Calcium' => 'Moderate', 'Vitamin C' => 'Moderate'];
    }
}
