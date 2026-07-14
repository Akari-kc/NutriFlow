<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('foods', 'school_id')) {
            Schema::table('foods', function (Blueprint $table) {
                $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('foods', 'school_id')) {
            Schema::table('foods', function (Blueprint $table) {
                $table->dropConstrainedForeignId('school_id');
            });
        }
    }
};
