<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('growth_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('measured_at');
            $table->decimal('weight_kg',5,2)->nullable();
            $table->decimal('height_cm',5,2)->nullable();
            $table->decimal('bmi',5,2)->nullable();
            $table->string('bmi_flag')->nullable(); // e.g., normal, underweight, overweight
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_measurements');
    }
};
