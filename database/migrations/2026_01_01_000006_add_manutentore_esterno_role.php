<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE users MODIFY role ENUM('operatore','manutentore','manutentore_esterno','admin') NOT NULL DEFAULT 'operatore'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE users MODIFY role ENUM('operatore','manutentore','admin') NOT NULL DEFAULT 'operatore'"
        );
    }
};
