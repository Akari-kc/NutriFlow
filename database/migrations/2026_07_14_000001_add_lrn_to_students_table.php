<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('students', 'lrn')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->string('lrn', 50)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('students', 'lrn')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('lrn');
        });
    }
};
