<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // Prototype uses file sessions, so this legacy migration is intentionally empty.
    }

    public function down(): void
    {
        // Nothing to roll back.
    }
};
