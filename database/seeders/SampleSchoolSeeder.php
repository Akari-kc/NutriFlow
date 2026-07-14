<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\{School, User, Student, GrowthMeasurement, Meal, MealItem, Food};

class SampleSchoolSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure foods
        if (Food::count() === 0) {
            (new FoodSeeder())->run();
        }
        $foods = Food::select('id','name','portion','kcal','protein_g')->get();
        if ($foods->isEmpty()) return;

        // Create Sample School
        $school = School::firstOrCreate(
            ['name' => 'Sample School'],
            [
                'street' => '100 Sample Ave',
                'city' => 'Makati',
                'region' => 'NCR',
                'address' => '100 Sample Ave, Makati, NCR',
            ]
        );

        // Create aide
        $email = 'sample.aide@sample.com';
        $aide = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Nutrition Aide - Sample School',
                'username' => $email,
                'password' => 'aide1234',
                'role' => 'aide',
                'school_id' => $school->id,
            ]
        );

        // Build realistic student roster
        $sections = ['Blue','Red','Green','Yellow'];
        $classes = ['Grade 3','Grade 4','Grade 5','Grade 6'];
        $firstNamesM = ['Daniel','Ethan','Liam','Noah','Lucas','Jacob','Mason','Logan'];
        $firstNamesF = ['Olivia','Emma','Ava','Sophia','Isabella','Mia','Charlotte','Amelia'];
        $lastNames = ['Dela Cruz','Santos','Reyes','Garcia','Lopez','Ramos','Torres','Flores'];

        $students = [];
        $count = 12;
        for ($i=0;$i<$count;$i++) {
            $gender = (random_int(0,1)===1) ? 'Male' : 'Female';
            $first = $gender==='Male' ? $firstNamesM[array_rand($firstNamesM)] : $firstNamesF[array_rand($firstNamesF)];
            $last = $lastNames[array_rand($lastNames)];
            $birthdate = Carbon::today()->subYears(random_int(7,12))->subDays(random_int(0,364))->toDateString();

            $student = Student::firstOrCreate(
                ['name' => $first.' '.$last, 'birthdate' => $birthdate],
                [
                    'gender' => $gender,
                    'section' => $sections[array_rand($sections)],
                    'class_name' => $classes[array_rand($classes)],
                    'school_id' => $school->id,
                ]
            );
            $students[] = $student;

            // Growth measurement (latest)
            $height = random_int(120, 155);
            $weight = random_int(20, 45);
            $bmi = round($weight / pow($height/100, 2), 2);
            $flag = $bmi < 14 ? 'Underweight' : 'Normal';
            GrowthMeasurement::create([
                'student_id' => $student->id,
                'measured_at' => Carbon::today()->subDays(random_int(0, 15))->toDateString(),
                'weight_kg' => $weight,
                'height_cm' => $height,
                'bmi' => $bmi,
                'bmi_flag' => $flag,
            ]);
        }

        // Meals for last 30 days: random attendance and kcal via items+quantity
        $foodIds = $foods->pluck('id')->all();
        foreach ($students as $st) {
            for ($d=29; $d>=0; $d--) {
                if (random_int(1,100) <= 70) { // 70% chance served
                    $servedAt = Carbon::today()->subDays($d)->setTime(random_int(7,13), random_int(0,59));
                    $types = ['Breakfast','Lunch','Snack'];
                    $meal = Meal::create([
                        'student_id' => $st->id,
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
