<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_requests', 'device_token')) {
                $table->dropIndex(['device_token']);
                $table->dropColumn('device_token');
            }
            // Reparto scelto dall'operatore all'accesso: raggruppa la visibilità
            // delle richieste. NON viene mostrato ai manutentori (a loro serve
            // solo il "reparto" del modulo).
            $table->string('reparto_accesso')->nullable()->after('reparto');
            $table->index('reparto_accesso');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropIndex(['reparto_accesso']);
            $table->dropColumn('reparto_accesso');
            $table->string('device_token', 64)->nullable()->after('created_by');
            $table->index('device_token');
        });
    }
};
