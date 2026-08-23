<?php

namespace App\Contracts;

interface NutritionRiskPredictor
{
    /**
     * @param  array<string, float|int|string>  $features
     * @return array<string, mixed>
     */
    public function predict(array $features): array;

    /**
     * @param  array<int, array<string, float|int|string>>  $featureRows
     * @return array<int, array<string, mixed>>
     */
    public function predictMany(array $featureRows): array;
}
