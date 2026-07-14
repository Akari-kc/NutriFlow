<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{School, User, Student, GrowthMeasurement, Meal, MealItem, Food};

class XyzSchoolSeeder extends Seeder
{
    public function run(): void
    {
        // Create XYZ Academy school
        $school = School::firstOrCreate(
            ['name' => 'XYZ Academy'],
            [
                'street' => '123 Sample St',
                'city' => 'Quezon City',
                'region' => 'NCR',
                'address' => '123 Sample St, Quezon City, NCR',
            ]
        );

        // Create aide for XYZ
        $aide = User::firstOrCreate(
            ['email' => 'xyz.aide@sample.com'],
            [
                'name' => 'Nutrition Aide - XYZ',
                'username' => 'xyz.aide@sample.com',
                'password' => 'aide1234',
                'role' => 'aide',
                'school_id' => $school->id,
            ]
        );

        // Students for XYZ
        $studentsData = [
            ['name' => 'XYZ Student One', 'gender' => 'Female', 'birthdate' => '2016-08-01', 'section' => 'Aqua', 'class_name' => 'Grade 3'],
            ['name' => 'XYZ Student Two', 'gender' => 'Male', 'birthdate' => '2015-03-22', 'section' => 'Amber', 'class_name' => 'Grade 4'],
            ['name' => 'XYZ Student Three', 'gender' => 'Female', 'birthdate' => '2014-01-10', 'section' => 'Emerald', 'class_name' => 'Grade 5'],
        ];

        $students = [];
        foreach ($studentsData as $s) {
            $student = Student::firstOrCreate(
                ['name' => $s['name'], 'birthdate' => $s['birthdate']],
                $s + ['school_id' => $school->id]
            );
            if (!$student->school_id) {
                $student->school_id = $school->id;
                $student->save();
            }
            $students[] = $student;
        }

        // Growth measurements
        $today = now()->toDateString();
        $gmPayloads = [
            ['weight_kg' => 21.0, 'height_cm' => 136.0], // Underweight
            ['weight_kg' => 33.0, 'height_cm' => 140.0], // Normal
            ['weight_kg' => 36.0, 'height_cm' => 142.0], // Normal
        ];
        foreach ($students as $idx => $st) {
            $w = $gmPayloads[$idx]['weight_kg'];
            $h_cm = $gmPayloads[$idx]['height_cm'];
            $h_m = $h_cm / 100;
            $bmi = round($w / max(0.0001, ($h_m * $h_m)), 2);
            $flag = $bmi < 14 ? 'Underweight' : 'Normal';
            GrowthMeasurement::create([
                'student_id' => $st->id,
                'measured_at' => $today,
                'weight_kg' => $w,
                'height_cm' => $h_cm,
                'bmi' => $bmi,
                'bmi_flag' => $flag,
            ]);
        }

        // Ensure foods exist
        $foods = [
            Food::where('name','Vegetable Soup')->first(),
            Food::where('name','Chicken Salad')->first(),
            Food::where('name','Egg Sandwich')->first(),
            Food::where('name','Fruit Salad')->first(),
            Food::where('name','Milk')->first(),
        ];
        $foods = array_values(array_filter($foods));
        if (count($foods) === 0) {
            (new FoodSeeder())->run();
            $foods = [
                Food::where('name','Vegetable Soup')->first(),
                Food::where('name','Chicken Salad')->first(),
                Food::where('name','Egg Sandwich')->first(),
                Food::where('name','Fruit Salad')->first(),
                Food::where('name','Milk')->first(),
            ];
            $foods = array_values(array_filter($foods));
        }

        // Meals for XYZ students
        foreach ($students as $s) {
            $meal = Meal::create([
                'student_id' => $s->id,
                'logged_by_user_id' => $aide->id,
                'meal_type' => 'Lunch',
                'served_at' => now()->setTime(12, 30),
            ]);

            foreach (array_slice($foods, 1, 3) as $f) {
                MealItem::create([
                    'meal_id' => $meal->id,
                    'food_id' => $f->id,
                    'portion_text' => $f->portion ?? null,
                    'quantity' => 1,
                ]);
            }
        }
    }
}
