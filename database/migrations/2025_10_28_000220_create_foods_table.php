<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('portion')->nullable();
            $table->decimal('kcal',8,2)->default(0);
            $table->decimal('protein_g',8,2)->default(0);
            $table->decimal('carbs_g',8,2)->default(0);
            $table->decimal('fat_g',8,2)->default(0);
            $table->decimal('iron_mg',8,2)->default(0);
            $table->decimal('vit_a_iu',10,2)->default(0);
            $table->decimal('vit_c_mg',8,2)->default(0);
            $table->decimal('calcium_mg',8,2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
