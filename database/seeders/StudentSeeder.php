<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use Illuminate\Support\Carbon;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            ['name' => 'Juan Dela Cruz', 'gender' => 'Male', 'birthdate' => '2015-03-10', 'section' => 'A', 'class_name' => 'Grade 4'],
            ['name' => 'Maria Santos', 'gender' => 'Female', 'birthdate' => '2016-06-21', 'section' => 'B', 'class_name' => 'Grade 3'],
            ['name' => 'Pedro Reyes', 'gender' => 'Male', 'birthdate' => '2014-11-05', 'section' => 'C', 'class_name' => 'Grade 5'],
            ['name' => 'Ana Lopez', 'gender' => 'Female', 'birthdate' => '2015-09-18', 'section' => 'A', 'class_name' => 'Grade 4'],
            ['name' => 'Jose Ramos', 'gender' => 'Male', 'birthdate' => '2016-01-30', 'section' => 'B', 'class_name' => 'Grade 3'],
        ];

        foreach ($students as $s) {
            Student::firstOrCreate(
                ['name' => $s['name'], 'birthdate' => $s['birthdate']],
                $s
            );
        }
    }
}
