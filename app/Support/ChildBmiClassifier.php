<?php

namespace App\Support;

use App\Models\GrowthMeasurement;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ChildBmiClassifier
{
    public const SEVERELY_UNDERNOURISHED = 'Severely Undernourished';
    public const UNDERNOURISHED = 'Undernourished';
    public const NORMAL = 'Normal';
    public const OVERWEIGHT = 'Overweight';
    public const OBESE = 'Obese';
    public const NEEDS_REVIEW = 'Needs Review';
    public const NO_MEASUREMENT = 'No Measurement';

    public const STATUSES = [
        self::NORMAL,
        self::UNDERNOURISHED,
        self::SEVERELY_UNDERNOURISHED,
        self::OVERWEIGHT,
        self::OBESE,
        self::NEEDS_REVIEW,
        self::NO_MEASUREMENT,
    ];

    private const WHO_BMI_FOR_AGE = [
        'Female' => [
            [61, 11.77, 12.75, 16.87, 18.86],
            [72, 11.72, 12.70, 17.01, 19.22],
            [84, 11.75, 12.73, 17.29, 19.79],
            [96, 11.88, 12.88, 17.73, 20.56],
            [108, 12.10, 13.14, 18.33, 21.51],
            [120, 12.38, 13.47, 19.03, 22.57],
            [132, 12.73, 13.88, 19.86, 23.73],
            [144, 13.15, 14.39, 20.81, 24.97],
            [156, 13.61, 14.94, 21.80, 26.21],
        ],
        'Male' => [
            [61, 12.12, 13.03, 16.64, 18.26],
            [72, 12.14, 13.04, 16.76, 18.52],
            [84, 12.25, 13.15, 17.05, 19.02],
            [96, 12.39, 13.30, 17.44, 19.68],
            [108, 12.56, 13.49, 17.91, 20.47],
            [120, 12.78, 13.73, 18.48, 21.40],
            [132, 13.05, 14.06, 19.16, 22.45],
            [144, 13.39, 14.45, 19.95, 23.58],
            [156, 13.80, 14.94, 20.83, 24.76],
        ],
    ];

    public static function classifyForStudent(Student $student, ?GrowthMeasurement $measurement): string
    {
        if (! $measurement || ! $measurement->bmi || (float) $measurement->bmi <= 0) {
            return self::NO_MEASUREMENT;
        }

        if (! $student->birthdate || ! $student->gender || ! $measurement->measured_at) {
            return self::NEEDS_REVIEW;
        }

        return self::classify(
            (float) $measurement->bmi,
            (string) $student->gender,
            $student->birthdate,
            $measurement->measured_at
        );
    }

    public static function classify(float $bmi, string $gender, CarbonInterface|string $birthdate, CarbonInterface|string $measuredAt): string
    {
        if ($bmi <= 0) {
            return self::NO_MEASUREMENT;
        }

        $sex = self::normalizeGender($gender);
        if (! $sex) {
            return self::NEEDS_REVIEW;
        }

        $ageMonths = self::ageInMonths($birthdate, $measuredAt);
        if ($ageMonths === null || $ageMonths < 0) {
            return self::NEEDS_REVIEW;
        }

        [$severeThinness, $thinness, $overweight, $obesity] = self::thresholds($sex, $ageMonths);

        if ($bmi < $severeThinness) {
            return self::SEVERELY_UNDERNOURISHED;
        }

        if ($bmi < $thinness) {
            return self::UNDERNOURISHED;
        }

        if ($bmi > $obesity) {
            return self::OBESE;
        }

        if ($bmi > $overweight) {
            return self::OVERWEIGHT;
        }

        return self::NORMAL;
    }

    public static function isUndernourished(?string $status): bool
    {
        return in_array($status, [self::UNDERNOURISHED, self::SEVERELY_UNDERNOURISHED], true);
    }

    public static function riskLevel(?string $status): string
    {
        return match ($status) {
            self::SEVERELY_UNDERNOURISHED => 'Severe',
            self::UNDERNOURISHED => 'Moderate',
            self::NO_MEASUREMENT, self::NEEDS_REVIEW => 'Review',
            default => 'Low',
        };
    }

    private static function thresholds(string $sex, int $ageMonths): array
    {
        $table = self::WHO_BMI_FOR_AGE[$sex];

        if ($ageMonths <= $table[0][0]) {
            return array_slice($table[0], 1);
        }

        $last = $table[count($table) - 1];
        if ($ageMonths >= $last[0]) {
            return array_slice($last, 1);
        }

        for ($i = 0; $i < count($table) - 1; $i++) {
            $lower = $table[$i];
            $upper = $table[$i + 1];

            if ($ageMonths >= $lower[0] && $ageMonths <= $upper[0]) {
                $ratio = ($ageMonths - $lower[0]) / ($upper[0] - $lower[0]);

                return [
                    self::interpolate($lower[1], $upper[1], $ratio),
                    self::interpolate($lower[2], $upper[2], $ratio),
                    self::interpolate($lower[3], $upper[3], $ratio),
                    self::interpolate($lower[4], $upper[4], $ratio),
                ];
            }
        }

        return array_slice($last, 1);
    }

    private static function interpolate(float $lower, float $upper, float $ratio): float
    {
        return $lower + (($upper - $lower) * $ratio);
    }

    private static function ageInMonths(CarbonInterface|string $birthdate, CarbonInterface|string $measuredAt): ?int
    {
        try {
            $birth = $birthdate instanceof CarbonInterface ? $birthdate : Carbon::parse($birthdate);
            $measured = $measuredAt instanceof CarbonInterface ? $measuredAt : Carbon::parse($measuredAt);

            return (int) floor($birth->diffInMonths($measured, false));
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normalizeGender(string $gender): ?string
    {
        return match (strtolower(trim($gender))) {
            'female', 'f', 'girl' => 'Female',
            'male', 'm', 'boy' => 'Male',
            default => null,
        };
    }
}
