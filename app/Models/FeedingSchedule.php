<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedingSchedule extends Model
{
    protected $fillable = [
        'school_id',
        'batch_name',
        'grade_range',
        'participant_student_ids',
        'selected_food_ids',
        'meal_type',
        'status',
        'session_date',
        'start_time',
        'end_time',
        'student_count',
        'assigned_aide',
        'menu_items',
        'notes',
    ];

    protected $casts = [
        'session_date' => 'date',
        'participant_student_ids' => 'array',
        'selected_food_ids' => 'array',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function participantNames(): array
    {
        $ids = collect($this->participant_student_ids)->filter()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return Student::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }

    public function selectedFoodNames(): array
    {
        $ids = collect($this->selected_food_ids)->filter()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return Food::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }
}
