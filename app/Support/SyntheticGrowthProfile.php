<?php

namespace App\Support;

use App\Models\Student;
use Carbon\CarbonInterface;
use RuntimeException;

class SyntheticGrowthProfile
{
    /**
     * Repeating ten-child allocation: 70% undernourished, 20% normal,
     * and 10% severely undernourished.
     */
    public static function statusForOrdinal(int $zeroBasedOrdinal): string
    {
        $slot = (($zeroBasedOrdinal % 10) + 10) % 10;

        return match (true) {
            $slot < 7 => ChildBmiClassifier::UNDERNOURISHED,
            $slot < 9 => ChildBmiClassifier::NORMAL,
            default => ChildBmiClassifier::SEVERELY_UNDERNOURISHED,
        };
    }

    /**
     * @return array{weight_kg: float, bmi: float, bmi_flag: string}
     */
    public static function measurementValues(
        Student $student,
        CarbonInterface|string $measuredAt,
        float $heightCm,
        string $targetStatus,
        float $progress = 1.0
    ): array {
        if (! $student->gender || ! $student->birthdate || $heightCm <= 0) {
            throw new RuntimeException('Synthetic growth profiles require gender, birthdate, and a valid height.');
        }

        $thresholds = ChildBmiClassifier::bmiThresholds(
            (string) $student->gender,
            $student->birthdate,
            $measuredAt
        );
        if (! $thresholds) {
            throw new RuntimeException('No child BMI-for-age thresholds are available for this synthetic record.');
        }

        $baseBmi = match ($targetStatus) {
            ChildBmiClassifier::SEVERELY_UNDERNOURISHED => $thresholds['severe_thinness'] - 0.65,
            ChildBmiClassifier::UNDERNOURISHED => ($thresholds['severe_thinness'] + $thresholds['thinness']) / 2,
            ChildBmiClassifier::NORMAL => ($thresholds['thinness'] + $thresholds['overweight']) / 2,
            default => throw new RuntimeException('Unsupported synthetic nutrition status.'),
        };

        $boundedProgress = max(0.0, min(1.0, $progress));
        $bmi = round($baseBmi + (($boundedProgress - 0.5) * 0.16), 2);
        $classifiedStatus = ChildBmiClassifier::classify(
            $bmi,
            (string) $student->gender,
            $student->birthdate,
            $measuredAt
        );

        if ($classifiedStatus !== $targetStatus) {
            $bmi = round($baseBmi, 2);
            $classifiedStatus = ChildBmiClassifier::classify(
                $bmi,
                (string) $student->gender,
                $student->birthdate,
                $measuredAt
            );
        }

        if ($classifiedStatus !== $targetStatus) {
            throw new RuntimeException('Unable to produce a consistent synthetic BMI classification.');
        }

        $weightKg = round($bmi * (($heightCm / 100) ** 2), 2);

        return [
            'weight_kg' => $weightKg,
            'bmi' => $bmi,
            'bmi_flag' => $classifiedStatus,
        ];
    }
}
