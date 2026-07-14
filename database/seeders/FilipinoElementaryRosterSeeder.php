<?php

namespace Database\Seeders;

use App\Models\GradeSection;
use App\Models\GrowthMeasurement;
use App\Models\Food;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FilipinoElementaryRosterSeeder extends Seeder
{
    public function run(): void
    {
        $school = $this->targetSchool();
        $aide = User::where('email', 'aide@example.com')->first() ?? User::where('role', 'aide')->first();
        if ($aide) {
            $aide->update(['school_id' => $school->id]);
        }

        $gradeSections = [
            'Kinder' => ['Sampaguita', 'Gumamela', 'Ilang-Ilang', 'Waling-Waling'],
            'Grade 1' => ['Mangga', 'Lansones', 'Rambutan', 'Atis'],
            'Grade 2' => ['Rizal', 'Bonifacio', 'Mabini', 'Luna'],
            'Grade 3' => ['Mercury', 'Venus', 'Earth', 'Mars'],
            'Grade 4' => ['Narra', 'Molave', 'Yakal', 'Acacia'],
            'Grade 5' => ['Sipag', 'Tiyaga', 'Talino', 'Bayanihan'],
            'Grade 6' => ['Magiting', 'Matapat', 'Masipag', 'Maalalahanin'],
        ];

        $firstNames = [
            'Male' => ['Andrei', 'Basti', 'Carlo', 'Daniel', 'Emilio', 'Francis', 'Gabriel', 'Hector', 'Ivan', 'Joaquin', 'Karlo', 'Lorenzo', 'Marco', 'Nico', 'Paolo'],
            'Female' => ['Althea', 'Bea', 'Celine', 'Danica', 'Elena', 'Faye', 'Gabriela', 'Hannah', 'Isabel', 'Janelle', 'Katrina', 'Lia', 'Mika', 'Nicole', 'Patricia'],
        ];
        $lastNames = ['Abad', 'Bautista', 'Castro', 'Dela Cruz', 'Enriquez', 'Flores', 'Garcia', 'Hernandez', 'Ilagan', 'Jimenez', 'Lopez', 'Mendoza', 'Navarro', 'Ocampo', 'Reyes', 'Santos', 'Torres', 'Villanueva'];
        $allergyPool = [null, null, null, 'Milk', 'Eggs', 'Peanuts', 'Soy', 'Fish'];

        DB::transaction(function () use ($school, $gradeSections, $firstNames, $lastNames, $allergyPool) {
            $this->ensureGradeSectionsTable();

            Student::where('school_id', $school->id)->delete();
            GradeSection::where('school_id', $school->id)->delete();

            $studentNumber = 1;
            $nameCounters = ['Male' => 0, 'Female' => 0];
            foreach ($gradeSections as $grade => $sections) {
                foreach ($sections as $section) {
                    GradeSection::create([
                        'school_id' => $school->id,
                        'class_name' => $grade,
                        'section' => $section,
                    ]);

                    for ($i = 0; $i < 10; $i++) {
                        $gender = $studentNumber % 2 === 0 ? 'Female' : 'Male';
                        $name = $this->uniqueStudentName($gender, $nameCounters[$gender], $firstNames, $lastNames);
                        $nameCounters[$gender]++;
                        $age = $this->ageForGrade($grade);
                        $birthdate = Carbon::today('Asia/Manila')
                            ->subYears($age)
                            ->subDays(($studentNumber * 11) % 330)
                            ->toDateString();

                        $student = Student::create([
                            'name' => $name,
                            'gender' => $gender,
                            'birthdate' => $birthdate,
                            'section' => $section,
                            'class_name' => $grade,
                            'school_id' => $school->id,
                            'allergies' => $allergyPool[$studentNumber % count($allergyPool)],
                        ]);

                        $this->seedGrowth($student, $grade, $studentNumber);
                        $studentNumber++;
                    }
                }
            }

            $this->seedMeals($school);
        });
    }

    private function uniqueStudentName(string $gender, int $index, array $firstNames, array $lastNames): string
    {
        $firstNameCount = count($firstNames[$gender]);
        $lastNameCount = count($lastNames);
        $first = $firstNames[$gender][$index % $firstNameCount];
        $last = $lastNames[(int) floor($index / $firstNameCount) % $lastNameCount];

        return $first.' '.$last;
    }

    private function targetSchool(): School
    {
        return School::updateOrCreate(
            ['name' => 'Eulogio Rodriguez Integrated School'],
            [
                'street' => 'Mandaluyong',
                'city' => 'Mandaluyong',
                'region' => 'NCR',
                'address' => 'Mandaluyong, NCR',
            ]
        );
    }

    private function ageForGrade(string $grade): int
    {
        return match ($grade) {
            'Kinder' => 5,
            'Grade 1' => 6,
            'Grade 2' => 7,
            'Grade 3' => 8,
            'Grade 4' => 9,
            'Grade 5' => 10,
            default => 11,
        };
    }

    private function seedGrowth(Student $student, string $grade, int $studentNumber): void
    {
        $gradeIndex = match ($grade) {
            'Kinder' => 0,
            'Grade 1' => 1,
            'Grade 2' => 2,
            'Grade 3' => 3,
            'Grade 4' => 4,
            'Grade 5' => 5,
            default => 6,
        };
        $baseHeight = 107 + ($gradeIndex * 6) + ($studentNumber % 5);
        $baseWeight = 17 + ($gradeIndex * 3.2) + (($studentNumber % 6) * .8);
        $days = [60, 45, 30, 15, 0];

        foreach ($days as $index => $daysAgo) {
            $height = round($baseHeight + ($index * .45), 1);
            $weight = round($baseWeight + ($index * .28), 2);
            $heightM = $height / 100;
            $bmi = round($weight / ($heightM * $heightM), 2);

            GrowthMeasurement::create([
                'student_id' => $student->id,
                'measured_at' => Carbon::today('Asia/Manila')->subDays($daysAgo)->toDateString(),
                'weight_kg' => $weight,
                'height_cm' => $height,
                'bmi' => $bmi,
                'bmi_flag' => $bmi < 18.5 ? 'Underweight' : 'Normal',
            ]);
        }
    }

    private function seedMeals(School $school): void
    {
        $this->copySharedFoodsToSchool($school);

        $aide = User::where('email', 'aide@example.com')->where('school_id', $school->id)->first()
            ?? User::where('role', 'aide')->where('school_id', $school->id)->first();
        $foods = Food::when(Schema::hasColumn('foods', 'school_id'), fn($q) => $q->where(function ($foodQ) use ($school) {
                $foodQ->where('school_id', $school->id)->orWhereNull('school_id');
            }))
            ->orderBy('name')
            ->take(8)
            ->get();

        if (!$aide || $foods->isEmpty()) {
            return;
        }

        $mealTypes = ['Breakfast', 'Lunch', 'Snack'];
        $times = ['Breakfast' => '07:30', 'Lunch' => '12:00', 'Snack' => '15:00'];

        Student::where('school_id', $school->id)
            ->orderBy('class_name')
            ->orderBy('section')
            ->orderBy('name')
            ->get()
            ->each(function (Student $student, int $index) use ($aide, $foods, $mealTypes, $times) {
                foreach ([5, 2, 0] as $mealIndex => $daysAgo) {
                    $mealType = $mealTypes[($index + $mealIndex) % count($mealTypes)];
                    $servedAt = Carbon::today('Asia/Manila')
                        ->subDays($daysAgo)
                        ->setTimeFromTimeString($times[$mealType]);

                    $meal = Meal::create([
                        'student_id' => $student->id,
                        'logged_by_user_id' => $aide->id,
                        'meal_type' => $mealType,
                        'served_at' => $servedAt,
                    ]);

                    for ($i = 0; $i < 2; $i++) {
                        $food = $foods[($index + $mealIndex + $i) % $foods->count()];
                        MealItem::create([
                            'meal_id' => $meal->id,
                            'food_id' => $food->id,
                            'quantity' => 1,
                            'portion_text' => $i === 0 ? '1 serving' : '1 piece',
                        ]);
                    }
                }
            });
    }

    private function copySharedFoodsToSchool(School $school): void
    {
        if (!Schema::hasColumn('foods', 'school_id')) {
            return;
        }

        Food::whereNull('school_id')->get()->each(function (Food $food) use ($school) {
            Food::updateOrCreate(
                [
                    'name' => $food->name,
                    'school_id' => $school->id,
                ],
                [
                    'portion' => $food->portion,
                    'kcal' => $food->kcal,
                    'protein_g' => $food->protein_g,
                    'carbs_g' => $food->carbs_g,
                    'fat_g' => $food->fat_g,
                    'iron_mg' => $food->iron_mg,
                    'vit_a_iu' => $food->vit_a_iu,
                    'vit_c_mg' => $food->vit_c_mg,
                    'calcium_mg' => $food->calcium_mg,
                    'recipe' => $food->recipe,
                ]
            );
        });

        Food::whereNull('school_id')->delete();
    }

    private function ensureGradeSectionsTable(): void
    {
        if (Schema::hasTable('grade_sections')) {
            return;
        }

        Schema::create('grade_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->string('class_name');
            $table->string('section');
            $table->timestamps();
            $table->unique(['school_id', 'class_name', 'section']);
        });
    }
}
