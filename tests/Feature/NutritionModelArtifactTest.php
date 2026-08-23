<?php

namespace Tests\Feature;

use App\Services\PythonNutritionRiskPredictor;
use Tests\TestCase;

class NutritionModelArtifactTest extends TestCase
{
    public function test_versioned_model_artifact_matches_its_metadata_hash(): void
    {
        $metadata = json_decode((string) file_get_contents(base_path('ml/models/model_metadata.json')), true);
        $artifact = base_path('ml/models/nutriflow_undernutrition_risk_v2.joblib');

        $this->assertSame('prototype-v2', $metadata['model_version']);
        $this->assertSame('Logistic Regression', $metadata['selected_model']);
        $this->assertSame($metadata['artifact_sha256'], hash_file('sha256', $artifact));
        $this->assertTrue($metadata['synthetic_data_only']);
        $this->assertFalse($metadata['clinical_validity']);
    }

    public function test_controlled_runner_matches_a_golden_case_when_test_runtime_is_configured(): void
    {
        $python = (string) getenv('NUTRIFLOW_ML_TEST_PYTHON');
        if ($python === '' || ! is_file($python)) {
            $this->markTestSkipped('Set NUTRIFLOW_ML_TEST_PYTHON to run Python parity validation.');
        }

        config([
            'nutriflow_ml.enabled' => true,
            'nutriflow_ml.python' => $python,
            'nutriflow_ml.pythonpath' => (string) getenv('NUTRIFLOW_ML_TEST_PYTHONPATH'),
        ]);
        $cases = json_decode((string) file_get_contents(base_path('ml/inference/golden_cases.json')), true);
        $case = $cases[0];

        $runner = app(PythonNutritionRiskPredictor::class);
        $result = $runner->predict($case['features']);

        $this->assertEqualsWithDelta(
            $case['expected_probability'],
            $result['future_undernutrition_probability'],
            1e-10
        );
        $this->assertSame($case['expected_class'], $result['predicted_class']);

        $batchResults = $runner->predictMany(collect($cases)->pluck('features')->all());
        $this->assertCount(count($cases), $batchResults);
        foreach ($cases as $index => $goldenCase) {
            $this->assertEqualsWithDelta(
                $goldenCase['expected_probability'],
                $batchResults[$index]['future_undernutrition_probability'],
                1e-10
            );
            $this->assertSame($goldenCase['expected_class'], $batchResults[$index]['predicted_class']);
        }
        $this->assertSame('prototype-v2', $result['model_version']);
    }
}
