<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            // Utente proprietario dell'iscrizione (manutentore/admin). Nullo per
            // gli operatori (accesso libero): in quel caso vale il reparto.
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            // Reparto d'accesso scelto dall'operatore, per recapitare le notifiche
            // ai dispositivi entrati con quel reparto.
            $table->string('reparto')->nullable()->index();
            // Endpoint del push service (può essere molto lungo): identificato in
            // modo univoco dall'hash.
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh');
            $table->string('auth');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
