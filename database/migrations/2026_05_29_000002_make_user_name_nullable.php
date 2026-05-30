<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE users MODIFY name VARCHAR(255) NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE users SET name = email WHERE name IS NULL OR name = ''");
        DB::statement('ALTER TABLE users MODIFY name VARCHAR(255) NOT NULL');
    }
};
