<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacUserSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('name', 'Eulogio Rodriguez Integrated School')->first();

        if (! $school) {
            return;
        }

        $aide = User::firstOrCreate(
            ['email' => 'nutrition.aide@example.com'],
            [
                'name' => 'Maria Santos',
                'username' => 'nutrition_aide',
                'password' => bcrypt('password'),
                'role' => User::ROLE_NUTRITION_AIDE,
                'school_id' => $school->id,
            ]
        );

        $aide->update([
            'name' => 'Maria Santos',
            'username' => 'nutrition_aide',
            'role' => User::ROLE_NUTRITION_AIDE,
            'school_id' => $school->id,
        ]);
    }
}
