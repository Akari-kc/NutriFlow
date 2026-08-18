<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $school = School::updateOrCreate(
            ['name' => 'Eulogio Rodriguez Integrated School'],
            [
                'street' => 'Mandaluyong',
                'city' => 'Mandaluyong',
                'region' => 'NCR',
                'address' => 'Mandaluyong, NCR',
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'aide@example.com'],
            [
                'name' => 'Isabel Greenfield',
                'username' => 'school_admin',
                'password' => bcrypt('password'),
                'role' => User::ROLE_SCHOOL_ADMIN,
                'school_id' => $school->id,
            ]
        );

        $admin->update([
            'name' => 'Isabel Greenfield',
            'username' => 'school_admin',
            'role' => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $school->id,
        ]);

        $this->call([
            RbacUserSeeder::class,
            FoodSeeder::class,
            FilipinoElementaryRosterSeeder::class,
        ]);
    }
}
