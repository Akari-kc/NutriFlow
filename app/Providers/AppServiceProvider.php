<?php

namespace App\Providers;

use App\Contracts\NutritionRiskPredictor;
use App\Services\PythonNutritionRiskPredictor;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NutritionRiskPredictor::class, PythonNutritionRiskPredictor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use Bootstrap 5 pagination UI globally (smaller, consistent arrows)
        Paginator::useBootstrapFive();

        // Ensure app name shows as NutriFlow in UI regardless of APP_NAME in .env
        // This avoids seeing 'Laravel' in the navbar if the env wasn't updated.
        config(['app.name' => 'NutriFlow']);
    }
}
