<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            // Momento entro cui il manutentore prevede di essere in reparto
            // per la sistemazione (impostato dal manutentore, visibile all'operatore).
            $table->timestamp('eta_intervento')->nullable()->after('taken_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn('eta_intervento');
        });
    }
};
