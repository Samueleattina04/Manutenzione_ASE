<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// --- Autenticazione ---
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// --- Area autenticata ---
Route::middleware('auth')->group(function () {
    // Richieste
    Route::get('/', [RequestController::class, 'index'])->name('richieste.index');
    Route::get('/richieste-elenco', [RequestController::class, 'listFragment'])->name('richieste.elenco');
    Route::get('/richieste/nuova', [RequestController::class, 'create'])->name('richieste.create');
    Route::post('/richieste', [RequestController::class, 'store'])->name('richieste.store');
    Route::get('/richieste/{richiesta}', [RequestController::class, 'show'])->name('richieste.show');
    Route::get('/richieste/{richiesta}/cronologia', [RequestController::class, 'timelineFragment'])->name('richieste.cronologia');
    Route::post('/richieste/{richiesta}/foto', [RequestController::class, 'storeAttachment'])->name('richieste.foto');

    // Azioni riservate ai manutentori/admin
    Route::middleware('role:manutentore,admin')->group(function () {
        Route::post('/richieste/{richiesta}/aggiornamenti', [RequestController::class, 'storeUpdate'])->name('richieste.aggiorna');
    });

    // Allegati (immagini protette da login)
    Route::get('/allegati/{attachment}', [AttachmentController::class, 'show'])->name('allegati.show');

    // Profilo
    Route::get('/profilo/password', [ProfileController::class, 'editPassword'])->name('profilo.password');
    Route::put('/profilo/password', [ProfileController::class, 'updatePassword'])->name('profilo.password.update');

    // Gestione utenti (solo admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/utenti', [UserController::class, 'index'])->name('utenti.index');
        Route::post('/utenti', [UserController::class, 'store'])->name('utenti.store');
        Route::put('/utenti/{user}', [UserController::class, 'update'])->name('utenti.update');
        Route::post('/utenti/{user}/stato', [UserController::class, 'toggle'])->name('utenti.toggle');
    });
});
