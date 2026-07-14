<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Food;

class FoodSeeder extends Seeder
{
    public function run(): void
    {
        // Prepared meals catalog (more realistic variety)
        $foods = [
            ['name' => 'Rice (steamed)', 'portion' => '1 cup', 'kcal' => 200, 'protein_g' => 4, 'carbs_g' => 45, 'fat_g' => 0.5, 'iron_mg' => 0.8, 'vit_a_iu' => 0, 'vit_c_mg' => 0, 'calcium_mg' => 10],
            ['name' => 'Chicken Adobo', 'portion' => '1 serving', 'kcal' => 300, 'protein_g' => 25, 'carbs_g' => 6, 'fat_g' => 18, 'iron_mg' => 1.5, 'vit_a_iu' => 150, 'vit_c_mg' => 2, 'calcium_mg' => 20],
            ['name' => 'Pancit Bihon', 'portion' => '1 plate', 'kcal' => 350, 'protein_g' => 12, 'carbs_g' => 55, 'fat_g' => 9, 'iron_mg' => 1.2, 'vit_a_iu' => 900, 'vit_c_mg' => 10, 'calcium_mg' => 40],
            ['name' => 'Vegetable Soup', 'portion' => '1 cup', 'kcal' => 95, 'protein_g' => 3.5, 'carbs_g' => 16, 'fat_g' => 2.0, 'iron_mg' => 1.6, 'vit_a_iu' => 2600, 'vit_c_mg' => 12, 'calcium_mg' => 40],
            ['name' => 'Chicken Salad', 'portion' => '1 bowl', 'kcal' => 220, 'protein_g' => 20, 'carbs_g' => 8, 'fat_g' => 12, 'iron_mg' => 1.4, 'vit_a_iu' => 900, 'vit_c_mg' => 15, 'calcium_mg' => 60],
            ['name' => 'Egg Sandwich', 'portion' => '1 sandwich', 'kcal' => 300, 'protein_g' => 14, 'carbs_g' => 32, 'fat_g' => 12, 'iron_mg' => 2.1, 'vit_a_iu' => 500, 'vit_c_mg' => 0, 'calcium_mg' => 120],
            ['name' => 'Fruit Salad', 'portion' => '1 bowl', 'kcal' => 150, 'protein_g' => 2, 'carbs_g' => 38, 'fat_g' => 0.5, 'iron_mg' => 0.4, 'vit_a_iu' => 400, 'vit_c_mg' => 45, 'calcium_mg' => 30],
            ['name' => 'Milk', 'portion' => '1 cup', 'kcal' => 120, 'protein_g' => 8, 'carbs_g' => 12, 'fat_g' => 5, 'iron_mg' => 0.1, 'vit_a_iu' => 500, 'vit_c_mg' => 0, 'calcium_mg' => 300],
            ['name' => 'Banana', 'portion' => '1 medium', 'kcal' => 105, 'protein_g' => 1.3, 'carbs_g' => 27, 'fat_g' => 0.4, 'iron_mg' => 0.3, 'vit_a_iu' => 80, 'vit_c_mg' => 10, 'calcium_mg' => 5],
            ['name' => 'Tuna Sandwich', 'portion' => '1 sandwich', 'kcal' => 280, 'protein_g' => 18, 'carbs_g' => 28, 'fat_g' => 10, 'iron_mg' => 1.6, 'vit_a_iu' => 200, 'vit_c_mg' => 2, 'calcium_mg' => 60],
            ['name' => 'Chicken Tinola', 'portion' => '1 bowl', 'kcal' => 240, 'protein_g' => 22, 'carbs_g' => 12, 'fat_g' => 11, 'iron_mg' => 1.4, 'vit_a_iu' => 1300, 'vit_c_mg' => 18, 'calcium_mg' => 55],
            ['name' => 'Ginisang Monggo', 'portion' => '1 bowl', 'kcal' => 260, 'protein_g' => 15, 'carbs_g' => 36, 'fat_g' => 7, 'iron_mg' => 3.2, 'vit_a_iu' => 700, 'vit_c_mg' => 9, 'calcium_mg' => 80],
            ['name' => 'Fish Sarciado', 'portion' => '1 serving', 'kcal' => 270, 'protein_g' => 24, 'carbs_g' => 10, 'fat_g' => 14, 'iron_mg' => 1.1, 'vit_a_iu' => 900, 'vit_c_mg' => 12, 'calcium_mg' => 90],
            ['name' => 'Tortang Talong', 'portion' => '1 piece', 'kcal' => 210, 'protein_g' => 10, 'carbs_g' => 14, 'fat_g' => 12, 'iron_mg' => 1.3, 'vit_a_iu' => 420, 'vit_c_mg' => 5, 'calcium_mg' => 45],
            ['name' => 'Arroz Caldo', 'portion' => '1 bowl', 'kcal' => 280, 'protein_g' => 16, 'carbs_g' => 40, 'fat_g' => 7, 'iron_mg' => 1.5, 'vit_a_iu' => 300, 'vit_c_mg' => 3, 'calcium_mg' => 35],
            ['name' => 'Sopas', 'portion' => '1 bowl', 'kcal' => 320, 'protein_g' => 17, 'carbs_g' => 42, 'fat_g' => 10, 'iron_mg' => 1.6, 'vit_a_iu' => 1100, 'vit_c_mg' => 8, 'calcium_mg' => 120],
            ['name' => 'Beef Nilaga', 'portion' => '1 bowl', 'kcal' => 330, 'protein_g' => 26, 'carbs_g' => 18, 'fat_g' => 17, 'iron_mg' => 2.5, 'vit_a_iu' => 800, 'vit_c_mg' => 16, 'calcium_mg' => 70],
            ['name' => 'Lumpiang Sariwa', 'portion' => '1 roll', 'kcal' => 190, 'protein_g' => 8, 'carbs_g' => 28, 'fat_g' => 6, 'iron_mg' => 1.2, 'vit_a_iu' => 1600, 'vit_c_mg' => 20, 'calcium_mg' => 65],
            ['name' => 'Turon', 'portion' => '1 piece', 'kcal' => 180, 'protein_g' => 2, 'carbs_g' => 34, 'fat_g' => 5, 'iron_mg' => 0.6, 'vit_a_iu' => 80, 'vit_c_mg' => 7, 'calcium_mg' => 20],
            ['name' => 'Buko Pandan', 'portion' => '1 cup', 'kcal' => 210, 'protein_g' => 4, 'carbs_g' => 38, 'fat_g' => 6, 'iron_mg' => 0.5, 'vit_a_iu' => 160, 'vit_c_mg' => 4, 'calcium_mg' => 90],
        ];
        foreach ($foods as $f) {
            Food::updateOrCreate(['name' => $f['name']], $f);
        }
    }
}
