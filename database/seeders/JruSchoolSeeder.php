<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use App\Models\{School, User, Student, GrowthMeasurement, Meal, MealItem, Food};

class JruSchoolSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Ensure School exists (with address fields if available)
        $school = School::firstOrCreate(
            ['name' => 'JRU'],
            [
                'street' => '793 Shaw Blvd',
                'city' => 'Mandaluyong',
                'region' => 'NCR',
                'address' => '793 Shaw Blvd, Mandaluyong, NCR',
            ]
        );

        // 2) Ensure Aide user exists for JRU
        $aide = User::firstOrCreate(
            ['email' => 'JRU@sample.com'],
            [
                'name' => 'Nutrition Aide - JRU',
                'username' => 'JRU@sample.com',
                'password' => 'aide1234', // hashed via model cast
                'role' => 'aide',
                'school_id' => $school->id,
            ]
        );

        // 3) Create several students for JRU
        $studentsData = [
            ['name' => 'JRU Student One', 'gender' => 'Male', 'birthdate' => '2016-05-14', 'section' => 'Blue', 'class_name' => 'Grade 3'],
            ['name' => 'JRU Student Two', 'gender' => 'Female', 'birthdate' => '2015-12-02', 'section' => 'Red', 'class_name' => 'Grade 4'],
            ['name' => 'JRU Student Three', 'gender' => 'Male', 'birthdate' => '2014-09-20', 'section' => 'Green', 'class_name' => 'Grade 5'],
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

        // 4) Add growth measurements (one underweight, others normal)
        // BMI = kg / (m^2)
        $today = now()->toDateString();
        $gmPayloads = [
            // Underweight case: low BMI
            ['weight_kg' => 20.0, 'height_cm' => 135.0], // BMI ~ 10.96
            // Normal-ish cases
            ['weight_kg' => 30.0, 'height_cm' => 138.0], // BMI ~ 15.76
            ['weight_kg' => 34.0, 'height_cm' => 140.0], // BMI ~ 17.35
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

        // 5) Ensure foods exist and collect them
        $foods = [
            Food::where('name','Vegetable Soup')->first(),
            Food::where('name','Chicken Salad')->first(),
            Food::where('name','Egg Sandwich')->first(),
            Food::where('name','Fruit Salad')->first(),
            Food::where('name','Milk')->first(),
        ];
        $foods = array_values(array_filter($foods));
        if (count($foods) === 0) {
            // Fallback: ensure foods are present by running FoodSeeder logic
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
        // 6) Create meals for the last 21 days with randomness for realistic intake
        $foodIds = array_map(fn($f)=>$f->id, $foods);
        foreach ($students as $s) {
            for ($d=20; $d>=0; $d--) {
                if (random_int(1,100) <= 75) { // 75% attendance
                    $servedAt = now()->subDays($d)->setTime(random_int(7,13), random_int(0,59));
                    $types = ['Breakfast','Lunch','Snack'];
                    $meal = Meal::create([
                        'student_id' => $s->id,
                        'logged_by_user_id' => $aide->id,
                        'meal_type' => $types[array_rand($types)],
                        'served_at' => $servedAt,
                    ]);
                    shuffle($foodIds);
                    $items = array_slice($foodIds, 0, random_int(1,3));
                    foreach ($items as $fid) {
                        MealItem::create([
                            'meal_id' => $meal->id,
                            'food_id' => $fid,
                            'portion_text' => null,
                            'quantity' => random_int(1, 2),
                        ]);
                    }
                }
            }
        }
    }
}
