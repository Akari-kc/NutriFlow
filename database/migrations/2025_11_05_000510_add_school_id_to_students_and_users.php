<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('role')->constrained('schools')->nullOnDelete();
        });

        // Backfill: create default school and assign existing records if needed
        if (!DB::table('schools')->exists()) {
            DB::table('schools')->insert(['name' => 'Main School', 'address' => null, 'created_at' => now(), 'updated_at' => now()]);
        }
        $defaultId = DB::table('schools')->value('id');
        DB::table('students')->whereNull('school_id')->update(['school_id' => $defaultId]);
        // Assign aides/admins can be null; assign aides without school to default if present
        DB::table('users')->whereNull('school_id')->where('role', 'aide')->update(['school_id' => $defaultId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
        });
    }
};
