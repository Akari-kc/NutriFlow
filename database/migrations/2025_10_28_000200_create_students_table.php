<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('gender', ['Male','Female'])->nullable();
            $table->date('birthdate')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('section')->nullable();
            $table->string('class_name')->nullable();
            $table->foreignId('caregiver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
