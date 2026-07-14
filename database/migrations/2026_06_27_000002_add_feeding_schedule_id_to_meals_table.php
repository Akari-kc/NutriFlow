<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('meals') && !Schema::hasColumn('meals', 'feeding_schedule_id')) {
            Schema::table('meals', function (Blueprint $table) {
                $table->unsignedBigInteger('feeding_schedule_id')->nullable()->after('logged_by_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meals') && Schema::hasColumn('meals', 'feeding_schedule_id')) {
            Schema::table('meals', function (Blueprint $table) {
                $table->dropColumn('feeding_schedule_id');
            });
        }
    }
};
