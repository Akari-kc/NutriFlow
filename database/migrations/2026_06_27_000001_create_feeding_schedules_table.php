<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feeding_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->string('batch_name');
            $table->string('grade_range');
            $table->text('participant_student_ids')->nullable();
            $table->text('selected_food_ids')->nullable();
            $table->string('meal_type');
            $table->string('status')->default('Scheduled');
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('student_count')->default(0);
            $table->string('assigned_aide')->nullable();
            $table->text('menu_items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeding_schedules');
    }
};
