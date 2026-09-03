<?php

namespace App\Services;

use App\Contracts\NutritionRiskPredictor;
use RuntimeException;
use Symfony\Component\Process\Process;

class PythonNutritionRiskPredictor implements NutritionRiskPredictor
{
    public function predict(array $features): array
    {
        if (! config('nutriflow_ml.enabled')) {
            throw new RuntimeException('The prototype assessment is disabled.');
        }

        $scriptPath = (string) config('nutriflow_ml.script_path');
        $modelPath = (string) config('nutriflow_ml.model_path');
        $metadataPath = (string) config('nutriflow_ml.metadata_path');
        foreach ([$scriptPath, $modelPath, $metadataPath] as $requiredPath) {
            if (! is_file($requiredPath) || ! is_readable($requiredPath)) {
                throw new RuntimeException('The prototype model package is incomplete.');
            }
        }

        $metadata = json_decode((string) file_get_contents($metadataPath), true);
        if (! is_array($metadata) || empty($metadata['artifact_sha256'])) {
            throw new RuntimeException('The prototype model metadata is invalid.');
        }
        $actualHash = hash_file('sha256', $modelPath);
        if (! hash_equals((string) $metadata['artifact_sha256'], (string) $actualHash)) {
            throw new RuntimeException('The prototype model failed its integrity check.');
        }

        $windowsRoot = getenv('SystemRoot') ?: getenv('WINDIR') ?: 'C:\Windows';
        $environment = [
            'PYTHONPATH' => false,
            'PYTHONHOME' => false,
            'SystemRoot' => $windowsRoot,
            'WINDIR' => $windowsRoot,
        ];
        $pythonPath = trim((string) config('nutriflow_ml.pythonpath'));
        if ($pythonPath !== '') {
            $environment['PYTHONPATH'] = $pythonPath;
        }

        $inputPath = tempnam(storage_path('framework/cache'), 'nf-ml-');
        if ($inputPath === false) {
            throw new RuntimeException('The prototype assessment input could not be prepared.');
        }

        try {
            $payload = json_encode($this->legacyModelFeatures($features), JSON_THROW_ON_ERROR);
            if (file_put_contents($inputPath, $payload, LOCK_EX) === false) {
                throw new RuntimeException('The prototype assessment input could not be prepared.');
            }
            $process = new Process(
                [(string) config('nutriflow_ml.python'), $scriptPath, $inputPath],
                base_path(),
                $environment,
                null,
                (float) config('nutriflow_ml.timeout_seconds', 10)
            );
            $process->run();
        } finally {
            if (is_file($inputPath)) {
                @unlink($inputPath);
            }
        }

        $decoded = json_decode(trim($process->getOutput()), true);
        if (! $process->isSuccessful() || ! is_array($decoded) || ! ($decoded['ok'] ?? false)) {
            $diagnostic = is_array($decoded) ? (string) ($decoded['error'] ?? '') : trim($process->getErrorOutput());
            $diagnostic = substr((string) preg_replace('/[\r\n]+/', ' ', $diagnostic), 0, 2000);

            throw new RuntimeException('The prototype assessment service is temporarily unavailable.'
                .($diagnostic !== '' ? ' Runtime detail: '.$diagnostic : ''));
        }

        $result = $decoded['result'] ?? null;
        $probability = is_array($result) ? ($result['future_undernutrition_probability'] ?? null) : null;
        if (! is_numeric($probability) || $probability < 0 || $probability > 1) {
            throw new RuntimeException('The prototype assessment returned an invalid result.');
        }

        return $result;
    }

    public function predictMany(array $featureRows): array
    {
        if ($featureRows === []) {
            return [];
        }

        if (! config('nutriflow_ml.enabled')) {
            throw new RuntimeException('The prototype assessment is disabled.');
        }

        $scriptPath = (string) config('nutriflow_ml.script_path');
        $modelPath = (string) config('nutriflow_ml.model_path');
        $metadataPath = (string) config('nutriflow_ml.metadata_path');
        foreach ([$scriptPath, $modelPath, $metadataPath] as $requiredPath) {
            if (! is_file($requiredPath) || ! is_readable($requiredPath)) {
                throw new RuntimeException('The prototype model package is incomplete.');
            }
        }

        $metadata = json_decode((string) file_get_contents($metadataPath), true);
        if (! is_array($metadata) || empty($metadata['artifact_sha256'])) {
            throw new RuntimeException('The prototype model metadata is invalid.');
        }
        $actualHash = hash_file('sha256', $modelPath);
        if (! hash_equals((string) $metadata['artifact_sha256'], (string) $actualHash)) {
            throw new RuntimeException('The prototype model failed its integrity check.');
        }

        $windowsRoot = getenv('SystemRoot') ?: getenv('WINDIR') ?: 'C:\Windows';
        $environment = [
            'PYTHONPATH' => false,
            'PYTHONHOME' => false,
            'SystemRoot' => $windowsRoot,
            'WINDIR' => $windowsRoot,
        ];
        $pythonPath = trim((string) config('nutriflow_ml.pythonpath'));
        if ($pythonPath !== '') {
            $environment['PYTHONPATH'] = $pythonPath;
        }

        $inputPath = tempnam(storage_path('framework/cache'), 'nf-ml-');
        if ($inputPath === false) {
            throw new RuntimeException('The prototype assessment input could not be prepared.');
        }

        try {
            $payload = json_encode([
                'batch' => array_values(array_map(
                    fn (array $features) => $this->legacyModelFeatures($features),
                    $featureRows
                )),
            ], JSON_THROW_ON_ERROR);
            if (file_put_contents($inputPath, $payload, LOCK_EX) === false) {
                throw new RuntimeException('The prototype assessment input could not be prepared.');
            }
            $process = new Process(
                [(string) config('nutriflow_ml.python'), $scriptPath, $inputPath],
                base_path(),
                $environment,
                null,
                (float) config('nutriflow_ml.timeout_seconds', 10)
            );
            $process->run();
        } finally {
            if (is_file($inputPath)) {
                @unlink($inputPath);
            }
        }

        $decoded = json_decode(trim($process->getOutput()), true);
        if (! $process->isSuccessful() || ! is_array($decoded) || ! ($decoded['ok'] ?? false)) {
            $diagnostic = is_array($decoded) ? (string) ($decoded['error'] ?? '') : trim($process->getErrorOutput());
            $diagnostic = substr((string) preg_replace('/[\r\n]+/', ' ', $diagnostic), 0, 2000);

            throw new RuntimeException('The prototype assessment service is temporarily unavailable.'
                .($diagnostic !== '' ? ' Runtime detail: '.$diagnostic : ''));
        }

        $results = $decoded['result'] ?? null;
        if (! is_array($results) || count($results) !== count($featureRows)) {
            throw new RuntimeException('The prototype assessment returned an invalid batch result.');
        }

        foreach ($results as $result) {
            $probability = is_array($result) ? ($result['future_undernutrition_probability'] ?? null) : null;
            if (! is_numeric($probability) || $probability < 0 || $probability > 1) {
                throw new RuntimeException('The prototype assessment returned an invalid result.');
            }
        }

        return array_values($results);
    }

    /**
     * Prototype-v2 was serialized with the former display vocabulary. Keep that
     * translation isolated at the legacy artifact boundary.
     */
    private function legacyModelFeatures(array $features): array
    {
        $features['current_bmi_flag'] = match ($features['current_bmi_flag'] ?? null) {
            'Wasted' => 'Undernourished',
            'Severely Wasted' => 'Severely Undernourished',
            default => $features['current_bmi_flag'] ?? null,
        };

        return $features;
    }
}
