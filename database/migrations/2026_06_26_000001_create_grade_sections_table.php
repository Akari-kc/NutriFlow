<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('grade_sections')) {
            return;
        }

        Schema::create('grade_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('class_name');
            $table->string('section');
            $table->timestamps();

            $table->unique(['school_id', 'class_name', 'section']);
        });

        DB::table('students')
            ->select('school_id', 'class_name', 'section')
            ->whereNotNull('class_name')
            ->whereNotNull('section')
            ->distinct()
            ->orderBy('class_name')
            ->orderBy('section')
            ->get()
            ->each(function ($row) {
                DB::table('grade_sections')->insertOrIgnore([
                    'school_id' => $row->school_id,
                    'class_name' => $row->class_name,
                    'section' => $row->section,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_sections');
    }
};
