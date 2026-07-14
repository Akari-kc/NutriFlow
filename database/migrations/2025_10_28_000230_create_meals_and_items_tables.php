<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('logged_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('meal_type', ['Breakfast','Lunch','Snack','Dinner']);
            $table->dateTime('served_at')->index();
            $table->timestamps();
        });

        Schema::create('meal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained('meals')->cascadeOnDelete();
            $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
            $table->string('portion_text')->nullable();
            $table->decimal('quantity',8,2)->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_items');
        Schema::dropIfExists('meals');
    }
};
