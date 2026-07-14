<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Create schools table if missing
        if (!Schema::hasTable('schools')) {
            Schema::create('schools', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('address')->nullable();
                $table->timestamps();
            });
        }

        // 2) Ensure address fields exist
        if (!Schema::hasColumn('schools', 'street')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->string('street')->nullable()->after('address');
            });
        }
        if (!Schema::hasColumn('schools', 'city')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->string('city')->nullable()->after('street');
            });
        }
        if (!Schema::hasColumn('schools', 'region')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->string('region')->nullable()->after('city');
            });
        }

        // 3) Seed a default school if table is empty
        $defaultId = DB::table('schools')->value('id');
        if (!$defaultId) {
            $defaultId = DB::table('schools')->insertGetId([
                'name' => 'Main School',
                'address' => null,
                'street' => null,
                'city' => null,
                'region' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4) Add school_id to students if missing and backfill
        if (Schema::hasTable('students') && !Schema::hasColumn('students', 'school_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            });
            // Backfill existing students to default school
            DB::table('students')->whereNull('school_id')->update(['school_id' => $defaultId]);
        }

        // 5) Add school_id to users if missing and backfill aides
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'school_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            });
            // Backfill aides to default school; leave admins/caregivers null
            try {
                DB::table('users')->whereNull('school_id')->where('role', 'aide')->update(['school_id' => $defaultId]);
            } catch (\Throwable $e) {
                // Role column might not exist on very old DBs; ignore
            }
        }
    }

    public function down(): void
    {
        // This is a sync migration; we won't drop columns/tables to avoid data loss
        // Optionally you could remove added columns here if needed
    }
};
