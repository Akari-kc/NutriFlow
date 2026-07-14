<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    use HasFactory;

    // Explicitly set to avoid singular table inference on some systems
    protected $table = 'foods';

    protected $fillable = [
        'name','portion','kcal','protein_g','carbs_g','fat_g','iron_mg','vit_a_iu','vit_c_mg','calcium_mg','school_id','recipe'
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function allergyAlerts(): array
    {
        $source = strtolower(implode(' ', [
            (string) $this->name,
            (string) $this->portion,
            (string) $this->recipe,
        ]));

        $rules = [
            'Milk/Dairy' => ['milk', 'dairy', 'cheese', 'cream', 'butter', 'yogurt', 'sopas', 'buko pandan', 'fruit salad'],
            'Egg' => ['egg', 'torta', 'tortang'],
            'Fish' => ['fish', 'tuna', 'sardine', 'sarciado'],
            'Shellfish' => ['shrimp', 'crab', 'shellfish', 'squid'],
            'Soy' => ['soy', 'tofu', 'tokwa', 'adobo', 'monggo'],
            'Wheat/Gluten' => ['wheat', 'gluten', 'flour', 'bread', 'sandwich', 'pancit', 'noodle', 'sopas'],
            'Peanuts/Tree Nuts' => ['peanut', 'cashew', 'almond', 'walnut', 'nut'],
            'Sesame' => ['sesame'],
            'Banana' => ['banana', 'turon'],
            'Corn' => ['corn'],
        ];

        $alerts = [];

        foreach ($rules as $label => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($source, $keyword)) {
                    $alerts[] = $label;
                    break;
                }
            }
        }

        return array_values(array_unique($alerts));
    }
}
