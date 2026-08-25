<?php

namespace Database\Seeders;

use App\Models\FeedingSchedule;
use App\Models\Food;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StandardizeFoodDisplayNamesSeeder extends Seeder
{
    public const NAME_MAP = [
        'Rice (steamed)' => 'Steamed Rice',
        'Chicken Adobo' => 'Adobong Manok',
        'Beef Nilaga' => 'Nilagang Baka',
        'Nilagang Baka (Beef and Vegetable Soup)' => 'Nilagang Baka',
        'Chicken Tinola' => 'Tinolang Manok',
        'Tinolang Manok (Chicken Ginger Soup)' => 'Tinolang Manok',
        'Ginisang Monggo' => 'Ginisang Munggo',
        'Ginisang Munggo (Sauteed Mung Beans)' => 'Ginisang Munggo',
        'Fish Sarciado' => 'Sarciadong Isda',
        'Sarciadong Isda (Fish in Tomato-Egg Sauce)' => 'Sarciadong Isda',
        'Tortang Talong (Eggplant Omelet)' => 'Tortang Talong',
        'Lumpiang Sariwa (Fresh Vegetable Spring Roll)' => 'Lumpiang Sariwa',
    ];

    public function run(): void
    {
        $renamedFoods = 0;
        $updatedSchedules = 0;

        DB::transaction(function () use (&$renamedFoods, &$updatedSchedules) {
            foreach (self::NAME_MAP as $oldName => $newName) {
                Food::where('name', $oldName)->get()->each(function (Food $food) use ($newName, &$renamedFoods) {
                    $food->forceFill(['name' => $newName])->saveQuietly();
                    $renamedFoods++;
                });
            }

            FeedingSchedule::query()
                ->whereNotNull('menu_items')
                ->orderBy('id')
                ->each(function (FeedingSchedule $schedule) use (&$updatedSchedules) {
                    $updatedMenu = collect(explode(',', (string) $schedule->menu_items))
                        ->map(fn ($menuItem) => $this->standardizeMenuItem((string) $menuItem))
                        ->filter()
                        ->implode(', ');

                    if ($updatedMenu === $schedule->menu_items) {
                        return;
                    }

                    $schedule->forceFill(['menu_items' => $updatedMenu])->saveQuietly();
                    $updatedSchedules++;
                });
        });

        $this->command?->info(
            "Standardized {$renamedFoods} food names and {$updatedSchedules} schedule menu snapshots."
        );
    }

    private function standardizeMenuItem(string $menuItem): string
    {
        $menuItem = trim($menuItem);

        foreach (self::NAME_MAP as $oldName => $standardName) {
            if ($menuItem === $oldName || $menuItem === $standardName || str_starts_with($menuItem, $oldName.' ')) {
                return $standardName;
            }
        }

        return $menuItem;
    }
}
