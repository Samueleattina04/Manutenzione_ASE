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
            // Super-amministratore: gestisce le impostazioni sensibili
            // (PIN operatori, codice di accesso) che gli altri admin non toccano.
            $table->boolean('is_super_admin')->default(false)->after('role');
        });

        // Gli amministratori già esistenti diventano super-admin (nessun blocco);
        // i nuovi admin creati in seguito NON lo sono, salvo assegnazione esplicita.
        DB::table('users')->where('role', 'admin')->update(['is_super_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
