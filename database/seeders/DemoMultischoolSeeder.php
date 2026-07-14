<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\{School, User, Student, GrowthMeasurement, Meal, MealItem, Food};

class DemoMultischoolSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure foods exist
        if (Food::count() === 0) {
            (new FoodSeeder())->run();
        }
        $foods = Food::select('id','name','portion','kcal','protein_g')->get();
        if ($foods->isEmpty()) return; // nothing to seed meals with

        $schoolsMeta = [
            ['name' => 'Greenfield Elementary', 'street' => '12 Oak Ave', 'city' => 'Makati', 'region' => 'NCR'],
            ['name' => 'Riverside Academy', 'street' => '98 River Rd', 'city' => 'Pasig', 'region' => 'NCR'],
            ['name' => 'Sunrise Primary', 'street' => '7 Sunrise St', 'city' => 'Manila', 'region' => 'NCR'],
            ['name' => 'Highland Prep', 'street' => '45 Summit Way', 'city' => 'Baguio', 'region' => 'CAR'],
            ['name' => 'Lakeside School', 'street' => '210 Lakeview Blvd', 'city' => 'Tagaytay', 'region' => 'CALABARZON'],
        ];

        $sections = ['A','B','C','D'];
        $classes = ['Grade 3','Grade 4','Grade 5','Grade 6'];

        foreach ($schoolsMeta as $meta) {
            // Create school
            $school = School::firstOrCreate(
                ['name' => $meta['name']],
                [
                    'street' => $meta['street'],
                    'city' => $meta['city'],
                    'region' => $meta['region'],
                    'address' => $meta['street'].' '.$meta['city'].' '.$meta['region'],
                ]
            );

            // Create aide
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i','.', $meta['name']));
            $email = $slug.'.aide@sample.com';
            $aide = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Nutrition Aide - '.$meta['name'],
                    'username' => $email,
                    'password' => 'aide1234',
                    'role' => 'aide',
                    'school_id' => $school->id,
                ]
            );

            // Students 10-15
            $studentCount = random_int(10, 15);
            $students = [];
            for ($i=0; $i<$studentCount; $i++) {
                $gender = (random_int(0,1)===1) ? 'Male' : 'Female';
                $firstNamesM = ['Juan','Pedro','Jose','Miguel','Carlos','Marco','Rafael','Diego'];
                $firstNamesF = ['Maria','Ana','Luisa','Sofia','Isabella','Camila','Gabriela','Elena'];
                $lastNames = ['Dela Cruz','Santos','Reyes','Garcia','Lopez','Ramos','Torres','Flores'];
                $first = $gender==='Male' ? $firstNamesM[array_rand($firstNamesM)] : $firstNamesF[array_rand($firstNamesF)];
                $last = $lastNames[array_rand($lastNames)];
                $birthdate = Carbon::today()->subYears(random_int(7,12))->subDays(random_int(0, 364))->toDateString();

                $student = Student::create([
                    'name' => $first.' '.$last,
                    'gender' => $gender,
                    'birthdate' => $birthdate,
                    'section' => $sections[array_rand($sections)],
                    'class_name' => $classes[array_rand($classes)],
                    'school_id' => $school->id,
                ]);
                $students[] = $student;

                // One latest growth measurement
                $height = random_int(120, 155); // cm
                $weight = random_int(20, 45);   // kg
                $bmi = round($weight / pow($height/100, 2), 2);
                $flag = $bmi < 14 ? 'Underweight' : 'Normal';
                GrowthMeasurement::create([
                    'student_id' => $student->id,
                    'measured_at' => Carbon::today()->subDays(random_int(0, 20))->toDateString(),
                    'weight_kg' => $weight,
                    'height_cm' => $height,
                    'bmi' => $bmi,
                    'bmi_flag' => $flag,
                ]);
            }

            // Meals for last 30 days
            $foodIds = $foods->pluck('id')->all();
            foreach ($students as $st) {
                for ($d=29; $d>=0; $d--) {
                    // 75% chance the student has a logged meal that day
                    if (random_int(1,100) <= 75) {
                        $servedAt = Carbon::today()->subDays($d)->setTime(12, random_int(0,59));
                        $meal = Meal::create([
                            'student_id' => $st->id,
                            'logged_by_user_id' => $aide->id,
                            'meal_type' => ['Breakfast','Lunch','Snack'][array_rand(['Breakfast','Lunch','Snack'])],
                            'served_at' => $servedAt,
                        ]);

                        // 2-3 items per meal
                        shuffle($foodIds);
                        $items = array_slice($foodIds, 0, random_int(2,3));
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
}
