<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NutritionAideController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\FeedingScheduleController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Models\User;

function nutriflowPrototypeLogin(): void
{
    if (Auth::check()) {
        return;
    }

    $user = User::where('role', 'aide')->first() ?? User::first();

    if ($user) {
        Auth::login($user);
    }
}

// Auth routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Default redirect
Route::get('/', function(){
    return redirect()->route('login');
});

Route::middleware(['auth'])->group(function(){
    Route::get('/dashboard', [NutritionAideController::class,'dashboard'])->name('dashboard');

    Route::get('/students', [StudentController::class,'index'])->name('students.index');
    Route::get('/students/create', [StudentController::class,'create'])->name('students.create');
    Route::post('/students', [StudentController::class,'store'])->name('students.store');
    Route::post('/students/sections', [StudentController::class,'storeSection'])->name('students.sections.store');
    Route::get('/students/{student}', [StudentController::class,'show'])->name('students.show');
    Route::get('/students/{student}/edit', [StudentController::class,'edit'])->name('students.edit');
    Route::patch('/students/{student}', [StudentController::class,'update'])->name('students.update');
    Route::delete('/students/{student}', [StudentController::class,'destroy'])->name('students.destroy');
    Route::post('/students/{student}/measurements', [StudentController::class,'storeMeasurement'])->name('students.measurements.store');

    Route::get('/meals', [MealController::class,'index'])->name('meals.index');
    Route::get('/meals/batch', [MealController::class,'batch'])->name('meals.batch');
    Route::post('/meals/batch', [MealController::class,'batchStore'])->name('meals.batch.store');
    Route::delete('/meals/{meal}', [MealController::class,'destroy'])->name('meals.destroy');

    Route::get('/feeding-schedules', [FeedingScheduleController::class,'index'])->name('feeding-schedules.index');
    Route::post('/feeding-schedules', [FeedingScheduleController::class,'store'])->name('feeding-schedules.store');
    Route::patch('/feeding-schedules/{feedingSchedule}', [FeedingScheduleController::class,'update'])->name('feeding-schedules.update');
    Route::delete('/feeding-schedules/{feedingSchedule}', [FeedingScheduleController::class,'destroy'])->name('feeding-schedules.destroy');

    Route::get('/menu-items', [FoodController::class,'index'])->name('menu-items.index');
    Route::get('/menu-items/create', [FoodController::class,'create'])->name('menu-items.create');
    Route::post('/menu-items', [FoodController::class,'store'])->name('menu-items.store');
    Route::get('/menu-items/{food}', [FoodController::class,'show'])->name('menu-items.show');
    Route::get('/menu-items/{food}/edit', [FoodController::class,'edit'])->name('menu-items.edit');
    Route::patch('/menu-items/{food}', [FoodController::class,'update'])->name('menu-items.update');
    Route::delete('/menu-items/{food}', [FoodController::class,'destroy'])->name('menu-items.destroy');
    Route::post('/menu-items/upload-csv', [FoodController::class,'uploadCsv'])->name('menu-items.uploadCsv');

    Route::get('/reports', [ReportController::class,'index'])->name('reports.index');
    Route::get('/reports/export/csv', [ReportController::class,'exportCsv'])->name('reports.export.csv');
    Route::get('/reports/export/pdf', [ReportController::class,'exportPdf'])->name('reports.export.pdf');
    Route::get('/settings', [SettingsController::class,'index'])->name('settings.index');
    Route::post('/settings/theme', [SettingsController::class,'updateTheme'])->name('settings.theme');
    Route::post('/settings/password', [SettingsController::class,'updatePassword'])->name('settings.password');
});
