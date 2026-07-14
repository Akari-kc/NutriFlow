<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Student, Meal, MealItem, Food, User};
use Illuminate\Support\Carbon;

class MealSeeder extends Seeder
{
    public function run(): void
    {
        $aide = User::where('role','aide')->first();
        if (!$aide) return;

    $students = Student::take(3)->get();
    $soup = Food::where('name','like','%Soup%')->first();
    $salad = Food::where('name','like','%Salad%')->first();
    $sandwich = Food::where('name','like','%Sandwich%')->first();

    if (!$soup && !$salad && !$sandwich) return; // minimal sanity: need at least one dish

        foreach ($students as $s) {
            $meal = Meal::create([
                'student_id' => $s->id,
                'logged_by_user_id' => $aide->id,
                'meal_type' => 'Lunch',
                'served_at' => Carbon::now()->setTime(12, 0),
            ]);

            if ($soup) { MealItem::create(['meal_id' => $meal->id, 'food_id' => $soup->id, 'portion_text' => '1 cup', 'quantity' => 1]); }
            if ($sandwich) { MealItem::create(['meal_id' => $meal->id, 'food_id' => $sandwich->id, 'portion_text' => '1 sandwich', 'quantity' => 1]); }
            if ($salad) { MealItem::create(['meal_id' => $meal->id, 'food_id' => $salad->id, 'portion_text' => '1 bowl', 'quantity' => 1]); }
        }
    }
}
