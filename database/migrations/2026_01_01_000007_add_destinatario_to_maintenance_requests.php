<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            // Destinatario della richiesta: interna / straordinaria / esterna
            $table->enum('destinatario', ['interna', 'straordinaria', 'esterna'])
                ->default('interna')->after('priorita');
            // Solo per "esterna": manutentore esterno a cui è assegnata la richiesta
            $table->foreignId('external_maintainer_id')->nullable()->after('assigned_to')
                ->constrained('users')->nullOnDelete();
            $table->index('destinatario');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropIndex(['destinatario']);
            $table->dropConstrainedForeignId('external_maintainer_id');
            $table->dropColumn('destinatario');
        });
    }
};
