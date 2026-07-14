<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\School;
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

        User::updateOrCreate([
            'email' => 'aide@example.com',
        ], [
            'name' => 'Isabel Greenfield',
            'username' => 'aide',
            'password' => bcrypt('password'),
            'role' => 'aide',
            'school_id' => $school->id,
        ]);

        $this->call([
            FoodSeeder::class,
            FilipinoElementaryRosterSeeder::class,
        ]);
    }
}
