<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedingProgramEnrollment extends Model
{
    protected $fillable = [
        'student_id',
        'school_year',
        'program_name',
        'milk_consent',
        'four_ps_status',
        'previous_sbfp_beneficiary',
        'data_origin',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
