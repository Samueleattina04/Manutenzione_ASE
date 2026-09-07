<?php

// Parametri di dominio dell'applicativo, ricalcati dal modulo Google
// originale "Richiesta Manutenzione".

return [
    // Nome dell'azienda (usato ad es. nelle email ai manutentori esterni).
    'azienda' => 'Antichi Sapori Dell\'Etna S.r.l',

    'impianti' => [
        'Dolciario Pisti',
        'Dolciario Vincente',
        'Dolciario Creme',
        'Dolciario Biscotti',
        'Dolciario Lievitati',
        'Dolciario Cioccolateria',
        'Frutta Secca 01',
        'Frutta Secca 104',
    ],

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

    // Destinatario della richiesta.
    'destinatari' => [
        'interna' => 'Manutenzione interna',
        'straordinaria' => 'Manutenzione straordinaria',
        'esterna' => 'Manutenzione esterna',
    ],

    // Etichette leggibili dei ruoli.
    'ruoli' => [
        'operatore' => 'Operatore',
        'manutentore' => 'Manutentore',
        'manutentore_esterno' => 'Manutentore esterno',
        'admin' => 'Amministratore',
    ],

    // Account condiviso usato dall'accesso libero degli operatori (senza password).
    'guest_operator_username' => 'operatore',

    // value => [etichetta, colore, rango per ordinamento]
    'priorita' => [
        'verde'  => ['label' => 'Verde – Bassa (entro 8 ore)',      'short' => 'Verde',  'color' => '#2e7d32', 'rank' => 1],
        'giallo' => ['label' => 'Giallo – Media (entro 4 ore)',     'short' => 'Giallo', 'color' => '#f9a825', 'rank' => 2],
        'rosso'  => ['label' => 'Rosso – Urgente (entro 30 minuti)', 'short' => 'Rosso',  'color' => '#c62828', 'rank' => 3],
    ],

    // Opzioni per il tempo di intervento previsto dal manutentore
    // ("entro quanto tempo sarà in reparto"): minuti => etichetta.
    'eta_opzioni' => [
        15  => 'Tra 15 minuti',
        30  => 'Tra 30 minuti',
        60  => 'Tra 1 ora',
        120 => 'Tra 2 ore',
        240 => 'Tra 4 ore',
        480 => 'Tra 8 ore',
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
