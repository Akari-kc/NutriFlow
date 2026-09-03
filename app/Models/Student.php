<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'learner_uid','name','lrn','source_learner_reference','data_origin','gender','birthdate','photo_path','section','class_name','school_id','allergies'
    ];

    protected $casts = [
        'birthdate' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Student $student) {
            if (! $student->learner_uid) {
                $student->learner_uid = self::allocateLearnerUid($student->school_id);
            }
        });
    }

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

    public function feedingProgramEnrollments(): HasMany
    {
        return $this->hasMany(FeedingProgramEnrollment::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return trim((string) $this->name)
            ?: trim((string) $this->source_learner_reference)
            ?: (string) $this->learner_uid;
    }

    public function getInitialsAttribute(): string
    {
        if (! trim((string) $this->name)) {
            return 'LR';
        }

        return collect(preg_split('/\s+/', trim((string) $this->name)) ?: [])
            ->filter()
            ->map(fn ($part) => strtoupper(substr((string) $part, 0, 1)))
            ->take(2)
            ->implode('');
    }

    private static function allocateLearnerUid(?int $schoolId): string
    {
        $schoolKey = $schoolId ? 'school-'.$schoolId : 'global';

        return DB::transaction(function () use ($schoolKey) {
            DB::table('learner_uid_sequences')->insertOrIgnore([
                'school_key' => $schoolKey,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('learner_uid_sequences')
                ->where('school_key', $schoolKey)
                ->lockForUpdate()
                ->first();
            $number = (int) $sequence->next_number;
            do {
                $candidate = 'LEARNER-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
                $alreadyUsed = self::query()
                    ->when(
                        $schoolKey === 'global',
                        fn ($query) => $query->whereNull('school_id'),
                        fn ($query) => $query->where('school_id', (int) str_replace('school-', '', $schoolKey))
                    )
                    ->where('learner_uid', $candidate)
                    ->exists();
                $number++;
            } while ($alreadyUsed);

            DB::table('learner_uid_sequences')
                ->where('school_key', $schoolKey)
                ->update([
                    'next_number' => $number,
                    'updated_at' => now(),
                ]);

            return $candidate;
        });
    }
}
