<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);       // 'impianto' | 'reparto'
            $table->string('value');
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->index(['type', 'position']);
        });

        // Popola con gli elenchi attuali definiti in config/manutenzione.php,
        // così su un'installazione esistente restano invariati e diventano
        // subito modificabili dall'amministratore.
        $now = now();
        $rows = [];
        foreach (['impianto' => 'manutenzione.impianti', 'reparto' => 'manutenzione.reparti'] as $type => $configKey) {
            foreach (array_values(config($configKey, [])) as $i => $value) {
                $rows[] = [
                    'type' => $type,
                    'value' => $value,
                    'position' => $i,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if ($rows) {
            DB::table('list_items')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('list_items');
    }
};
