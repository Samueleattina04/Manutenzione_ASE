<?php

// Parametri di dominio dell'applicativo, ricalcati dal modulo Google
// originale "Richiesta Manutenzione".

return [
    'impianti' => ['Pisti', 'Vincente', 'Madero 01', 'Madero 104'],

    'reparti' => [
        'Madero Produzione',
        'Flowpack',
        'Lievitati',
        'Magazzino',
        'Magazzino 01',
        'Magazzino 20',
        'Magazzino 135',
        'Celle',
        'Pisti Crema',
        'Pisti cioccolateria',
        'Pisti Croccanti e biscotti',
        'Pisti confezionamento e cella SL',
        'Mag Imballi',
        'Vincente',
        'Uffici',
        'Esterno',
    ],

    // Account condiviso usato dall'accesso libero degli operatori (senza password).
    'guest_operator_username' => 'operatore',

    // value => [etichetta, colore, rango per ordinamento]
    'priorita' => [
        'verde'  => ['label' => 'Verde – In coda',                       'short' => 'Verde',  'color' => '#2e7d32', 'rank' => 1],
        'giallo' => ['label' => 'Giallo – Entro la giornata',            'short' => 'Giallo', 'color' => '#f9a825', 'rank' => 2],
        'rosso'  => ['label' => 'Rosso – Blocco produzione. Urgente!!!', 'short' => 'Rosso',  'color' => '#c62828', 'rank' => 3],
    ],

    // ciclo di vita di una richiesta
    'stati' => [
        'aperta'               => ['label' => 'Aperta',                'color' => '#1565c0', 'done' => false],
        'presa_in_carico'      => ['label' => 'Presa in carico',       'color' => '#6a1b9a', 'done' => false],
        'in_corso'             => ['label' => 'In corso',              'color' => '#ef6c00', 'done' => false],
        'risolta_parzialmente' => ['label' => 'Risolta parzialmente',  'color' => '#9e9d24', 'done' => false],
        'risolta'              => ['label' => 'Risolta completamente',  'color' => '#2e7d32', 'done' => true],
        'chiusa'               => ['label' => 'Chiusa',                 'color' => '#546e7a', 'done' => true],
    ],

    // stati che un manutentore può assegnare
    'stati_manutentore' => [
        'presa_in_carico',
        'in_corso',
        'risolta_parzialmente',
        'risolta',
        'chiusa',
    ],
];
