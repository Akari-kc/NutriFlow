<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('learner_uid_sequences', function (Blueprint $table) {
            $table->string('school_key', 40)->primary();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('learner_uid', 32)->nullable()->after('id');
            $table->string('source_learner_reference', 100)->nullable()->after('lrn');
            $table->string('data_origin', 30)->default('Manual')->after('source_learner_reference');
        });

        $counters = [];
        DB::table('students')
            ->select('id', 'school_id')
            ->orderBy('school_id')
            ->orderBy('id')
            ->get()
            ->each(function ($student) use (&$counters) {
                $schoolKey = $student->school_id === null ? 'none' : (string) $student->school_id;
                $counters[$schoolKey] = ($counters[$schoolKey] ?? 0) + 1;

                DB::table('students')->where('id', $student->id)->update([
                    'learner_uid' => 'LEARNER-'.str_pad((string) $counters[$schoolKey], 3, '0', STR_PAD_LEFT),
                    'data_origin' => 'Synthetic',
                ]);
            });

        foreach ($counters as $schoolKey => $lastNumber) {
            DB::table('learner_uid_sequences')->insert([
                'school_key' => $schoolKey === 'none' ? 'global' : 'school-'.$schoolKey,
                'next_number' => $lastNumber + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('students', function (Blueprint $table) {
            $table->string('learner_uid', 32)->nullable(false)->change();
            $table->string('name')->nullable()->change();
            $table->unique(['school_id', 'learner_uid'], 'students_school_learner_uid_unique');
        });

        Schema::table('growth_measurements', function (Blueprint $table) {
            $table->string('assessment_phase', 30)->default('Additional Monitoring')->after('measured_at');
            $table->string('source_nutrition_status', 100)->nullable()->after('bmi_flag');
            $table->string('height_for_age_status', 100)->nullable()->after('source_nutrition_status');
            $table->string('assessment_method', 100)->nullable()->after('height_for_age_status');
            $table->string('data_origin', 30)->default('Manual')->after('assessment_method');
            $table->string('source_record_reference', 150)->nullable()->after('data_origin');
        });

        DB::table('growth_measurements')->orderBy('id')->get()->each(function ($measurement) {
            $normalized = match ($measurement->bmi_flag) {
                'Undernourished' => 'Wasted',
                'Severely Undernourished' => 'Severely Wasted',
                default => $measurement->bmi_flag,
            };

            DB::table('growth_measurements')->where('id', $measurement->id)->update([
                'source_nutrition_status' => $measurement->bmi_flag,
                'bmi_flag' => $normalized,
                'assessment_method' => 'NutriFlow BMI-for-age prototype',
                'data_origin' => 'Synthetic',
            ]);
        });

        DB::table('growth_measurements')
            ->select('student_id')
            ->distinct()
            ->pluck('student_id')
            ->each(function ($studentId) {
                $measurements = DB::table('growth_measurements')
                    ->where('student_id', $studentId)
                    ->orderBy('measured_at')
                    ->orderBy('id')
                    ->get();
                $lastIndex = $measurements->count() - 1;

                foreach ($measurements->values() as $index => $measurement) {
                    $phase = match (true) {
                        $index === 0 => 'Baseline',
                        $index === $lastIndex => 'Endline',
                        default => 'Additional Monitoring',
                    };
                    DB::table('growth_measurements')->where('id', $measurement->id)->update([
                        'assessment_phase' => $phase,
                    ]);
                }
            });

        Schema::create('feeding_program_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('school_year', 20);
            $table->string('program_name')->default('School-Based Feeding Program');
            $table->string('milk_consent', 20)->default('Not recorded');
            $table->string('four_ps_status', 20)->default('Not recorded');
            $table->string('previous_sbfp_beneficiary', 20)->default('Not recorded');
            $table->string('data_origin', 30)->default('Manual');
            $table->timestamps();
            $table->unique(['student_id', 'school_year'], 'feeding_enrollment_student_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeding_program_enrollments');
        Schema::dropIfExists('learner_uid_sequences');

        Schema::table('growth_measurements', function (Blueprint $table) {
            $table->dropColumn([
                'assessment_phase',
                'source_nutrition_status',
                'height_for_age_status',
                'assessment_method',
                'data_origin',
                'source_record_reference',
            ]);
        });

        DB::table('students')->whereNull('name')->orderBy('id')->get()->each(function ($student) {
            DB::table('students')->where('id', $student->id)->update([
                'name' => $student->learner_uid ?: 'Learner',
            ]);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_school_learner_uid_unique');
            $table->string('name')->nullable(false)->change();
            $table->dropColumn(['learner_uid', 'source_learner_reference', 'data_origin']);
        });
    }
};
