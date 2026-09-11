<?php

use App\Http\Controllers\AccessGateController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// --- Porta d'ingresso (codice di accesso aziendale, per dispositivo) ---
Route::get('/accesso', [AccessGateController::class, 'form'])->name('accesso.form');
Route::post('/accesso', [AccessGateController::class, 'submit'])
    ->middleware('throttle:10,1')->name('accesso.submit');

// --- Accesso ---
// Scelta del profilo (pagina iniziale per chi non è autenticato)
Route::get('/entra', [LoginController::class, 'chooser'])->name('entra');
// Operatore: accesso libero senza credenziali, previa scelta del reparto
Route::get('/entra/operatore', [LoginController::class, 'chooseReparto'])->name('entra.operatore.reparto');
Route::post('/entra/operatore', [LoginController::class, 'enterAsOperatore'])
    ->middleware('throttle:30,1')->name('entra.operatore');
// Manutentore / Amministratore: username e password
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// --- Area autenticata ---
Route::middleware('auth')->group(function () {
    // Richieste
    Route::get('/', [RequestController::class, 'index'])->name('richieste.index');
    Route::get('/richieste-elenco', [RequestController::class, 'listFragment'])->name('richieste.elenco');
    Route::get('/richieste/nuova', [RequestController::class, 'create'])->name('richieste.create');
    Route::get('/richieste/esporta', [RequestController::class, 'export'])->name('richieste.export');
    Route::post('/richieste', [RequestController::class, 'store'])->name('richieste.store');
    Route::get('/richieste/{richiesta}', [RequestController::class, 'show'])->name('richieste.show');
    Route::get('/richieste/{richiesta}/cronologia', [RequestController::class, 'timelineFragment'])->name('richieste.cronologia');
    Route::post('/richieste/{richiesta}/foto', [RequestController::class, 'storeAttachment'])->name('richieste.foto');
    Route::delete('/richieste/{richiesta}', [RequestController::class, 'destroy'])->name('richieste.destroy');

    // Azioni riservate ai manutentori (interni/esterni/straordinari) e admin
    Route::middleware('role:manutentore,manutentore_esterno,manutentore_straordinario,admin')->group(function () {
        Route::post('/richieste/{richiesta}/aggiornamenti', [RequestController::class, 'storeUpdate'])->name('richieste.aggiorna');
        Route::post('/richieste/{richiesta}/tempo-intervento', [RequestController::class, 'setEta'])->name('richieste.eta');
    });

    // Destinatario e assegnazione del manutentore (solo admin)
    Route::middleware('role:admin')->group(function () {
        Route::post('/richieste/{richiesta}/assegnazione', [RequestController::class, 'updateAssegnazione'])->name('richieste.assegnazione');
    });

    // Allegati (immagini protette da login)
    Route::get('/allegati/{attachment}', [AttachmentController::class, 'show'])->name('allegati.show');

    // Profilo
    Route::get('/profilo/password', [ProfileController::class, 'editPassword'])->name('profilo.password');
    Route::put('/profilo/password', [ProfileController::class, 'updatePassword'])->name('profilo.password.update');

    // Notifiche push (iscrizione del dispositivo)
    Route::get('/push/chiave', [PushController::class, 'key'])->name('push.key');
    Route::post('/push/iscrivi', [PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/annulla', [PushController::class, 'unsubscribe'])->name('push.unsubscribe');

    // Gestione utenti (solo admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/utenti', [UserController::class, 'index'])->name('utenti.index');
        Route::post('/utenti', [UserController::class, 'store'])->name('utenti.store');
        Route::put('/utenti/{user}', [UserController::class, 'update'])->name('utenti.update');
        Route::post('/utenti/{user}/stato', [UserController::class, 'toggle'])->name('utenti.toggle');
        Route::delete('/utenti/{user}', [UserController::class, 'destroy'])->name('utenti.destroy');

        // Impostazioni: elenchi modificabili (impianti, reparti)
        Route::get('/impostazioni', [SettingsController::class, 'index'])->name('impostazioni.index');
        Route::post('/impostazioni/codice-accesso', [SettingsController::class, 'updateAccessCode'])->name('impostazioni.access-code');
        Route::post('/impostazioni/voce', [SettingsController::class, 'storeItem'])->name('impostazioni.voce.store');
        Route::put('/impostazioni/voce/{listItem}', [SettingsController::class, 'updateItem'])->name('impostazioni.voce.update');
        Route::delete('/impostazioni/voce/{listItem}', [SettingsController::class, 'destroyItem'])->name('impostazioni.voce.destroy');
        Route::post('/impostazioni/voce/{listItem}/sposta', [SettingsController::class, 'moveItem'])->name('impostazioni.voce.move');
    });
});
