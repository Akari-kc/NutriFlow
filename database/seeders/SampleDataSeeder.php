<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Models\{User, Student, Food, Meal, MealItem};

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure aide exists
        $aide = User::firstOrCreate(
            ['username' => 'aide'],
            ['name' => 'Nutrition Aide','email' => 'aide@example.com','password' => Hash::make('password'),'role' => 'aide']
        );

        // Reset data (MySQL safe)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        MealItem::query()->delete();
        Meal::query()->delete();
        Student::query()->delete();
        Food::query()->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

    // Meals catalog (example dishes)
    $soup = Food::create(['name' => 'Vegetable Soup','portion'=>'1 cup','kcal'=>95,'protein_g'=>3.5,'carbs_g'=>16,'fat_g'=>2.0,'iron_mg'=>1.6,'vit_a_iu'=>2600,'vit_c_mg'=>12,'calcium_mg'=>40]);
    $salad = Food::create(['name' => 'Chicken Salad','portion'=>'1 bowl','kcal'=>220,'protein_g'=>20,'carbs_g'=>8,'fat_g'=>12,'iron_mg'=>1.4,'vit_a_iu'=>900,'vit_c_mg'=>15,'calcium_mg'=>60]);
    $sandwich = Food::create(['name' => 'Egg Sandwich','portion'=>'1 sandwich','kcal'=>300,'protein_g'=>14,'carbs_g'=>32,'fat_g'=>12,'iron_mg'=>2.1,'vit_a_iu'=>500,'vit_c_mg'=>0,'calcium_mg'=>120]);

        // Students (exactly 3)
        $students = [
            ['name' => 'Alex Cruz','gender'=>'Male','birthdate'=>'2015-03-10','section'=>'A','class_name'=>'Grade 4'],
            ['name' => 'Bianca Santos','gender'=>'Female','birthdate'=>'2016-06-21','section'=>'B','class_name'=>'Grade 3'],
            ['name' => 'Carlo Reyes','gender'=>'Male','birthdate'=>'2014-11-05','section'=>'C','class_name'=>'Grade 5'],
        ];
        $studentModels = [];
        foreach ($students as $s) {
            $studentModels[] = Student::create($s);
        }

        // Logged meals for today at 12:00 for each student
        $servedAt = Carbon::today()->setTime(12, 0);
        foreach ($studentModels as $s) {
            $meal = Meal::create([
                'student_id' => $s->id,
                'logged_by_user_id' => $aide->id,
                'meal_type' => 'Lunch',
                'served_at' => $servedAt,
            ]);
            // Example: soup + egg sandwich
            MealItem::create(['meal_id'=>$meal->id,'food_id'=>$soup->id,'portion_text'=>'1 cup','quantity'=>1]);
            MealItem::create(['meal_id'=>$meal->id,'food_id'=>$sandwich->id,'portion_text'=>'1 sandwich','quantity'=>1]);
        }
    }
}
