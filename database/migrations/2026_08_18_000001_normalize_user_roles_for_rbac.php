<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('nutrition_aide')->change();
        });

        DB::table('users')
            ->whereIn('role', ['aide', 'admin'])
            ->update(['role' => 'school_admin']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'school_admin')
            ->update(['role' => 'aide']);

        DB::table('users')
            ->where('role', 'nutrition_aide')
            ->update(['role' => 'aide']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'aide', 'caregiver'])->default('aide')->change();
        });
    }
};
