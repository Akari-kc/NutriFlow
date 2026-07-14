<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'name','gender','birthdate','photo_path','section','class_name','school_id','allergies'
    ];

    protected $casts = [
        'birthdate' => 'date',
    ];

    public function measurements(): HasMany
    {
        return $this->hasMany(GrowthMeasurement::class);
    }

    public function latestMeasurement(): HasOne
    {
        return $this->hasOne(GrowthMeasurement::class)->latestOfMany('measured_at');
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }
}
