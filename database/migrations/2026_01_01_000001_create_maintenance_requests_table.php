<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('impianto');
            $table->string('impianto_altro')->nullable();
            $table->string('macchinario'); // impianto o macchinario in questione
            $table->string('reparto')->nullable();
            $table->text('descrizione')->nullable(); // descrizione evento
            $table->enum('priorita', ['verde', 'giallo', 'rosso'])->default('verde');
            $table->text('note')->nullable();
            $table->string('operatore');
            $table->enum('status', [
                'aperta', 'presa_in_carico', 'in_corso',
                'risolta_parzialmente', 'risolta', 'chiusa',
            ])->default('aperta');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('taken_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('priorita');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
