<?php

namespace Tests\Feature;

use App\Models\FeedingSchedule;
use App\Models\Food;
use App\Models\School;
use Database\Seeders\StandardizeFoodDisplayNamesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodDisplayNameStandardizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renames_foods_and_schedule_snapshots_without_changing_ids_or_nutrition(): void
    {
        $school = School::create(['name' => 'Eulogio Rodriguez Integrated School']);
        $food = Food::create([
            'school_id' => $school->id,
            'name' => 'Ginisang Monggo',
            'portion' => '1 bowl',
        ]);
        $food->forceFill([
            'kcal' => 260,
            'protein_g' => 15,
            'carbs_g' => 36,
            'fat_g' => 7,
            'iron_mg' => 3.2,
            'vit_a_iu' => 700,
            'vit_c_mg' => 9,
            'calcium_mg' => 80,
        ])->save();
        $adobo = Food::create([
            'school_id' => $school->id,
            'name' => 'Chicken Adobo',
            'portion' => '1 serving',
        ]);

        $schedule = FeedingSchedule::create([
            'school_id' => $school->id,
            'batch_name' => 'Batch A',
            'grade_range' => 'Grade 3',
            'participant_student_ids' => [],
            'selected_food_ids' => [$food->id, $adobo->id],
            'meal_type' => 'Lunch',
            'status' => 'Scheduled',
            'session_date' => now('Asia/Manila')->toDateString(),
            'start_time' => '12:00',
            'end_time' => '12:30',
            'student_count' => 0,
            'menu_items' => 'Chicken Adobo, Ginisang Monggo, Rice (steamed), Tortang Talong, Lumpiang Sariwa',
        ]);

        $foodId = $food->id;
        $adoboId = $adobo->id;
        $nutritionBefore = $food->only([
            'portion',
            'kcal',
            'protein_g',
            'carbs_g',
            'fat_g',
            'iron_mg',
            'vit_a_iu',
            'vit_c_mg',
            'calcium_mg',
        ]);

        (new StandardizeFoodDisplayNamesSeeder)->run();
        (new StandardizeFoodDisplayNamesSeeder)->run();

        $food->refresh();
        $schedule->refresh();

        $this->assertSame($foodId, $food->id);
        $this->assertSame('Ginisang Munggo', $food->name);
        $this->assertSame($adoboId, $adobo->fresh()->id);
        $this->assertSame('Adobong Manok', $adobo->fresh()->name);
        $this->assertSame($nutritionBefore, $food->only(array_keys($nutritionBefore)));
        $this->assertSame([$foodId, $adoboId], $schedule->selected_food_ids);
        $this->assertSame(
            'Adobong Manok, Ginisang Munggo, Steamed Rice, Tortang Talong, Lumpiang Sariwa',
            $schedule->menu_items
        );
        $this->assertSame(2, Food::count());
    }
}
