<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealItem extends Model
{
    use HasFactory;

    protected $fillable = ['meal_id','food_id','portion_text','quantity'];

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
