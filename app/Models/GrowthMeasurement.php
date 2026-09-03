<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'measured_at',
        'assessment_phase',
        'weight_kg',
        'height_cm',
        'bmi',
        'bmi_flag',
        'source_nutrition_status',
        'height_for_age_status',
        'assessment_method',
        'data_origin',
        'source_record_reference',
    ];

    protected $casts = [
        'measured_at' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
