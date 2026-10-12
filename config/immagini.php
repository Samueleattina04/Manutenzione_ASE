<?php

// Impostazioni per l'ottimizzazione delle foto caricate (richieste e soluzioni).
// Le foto scattate dal telefono pesano spesso diversi MB: vengono ridimensionate
// e ricompresse al momento del caricamento per occupare molto meno spazio su disco,
// mantenendo una qualità più che sufficiente per la consultazione.

return [

    // Lato lungo massimo (in pixel) a cui ridurre le foto.
    // 1600px è ampiamente sufficiente per vedere bene un guasto o una soluzione.
    // Imposta 0 per non ridimensionare (sconsigliato).
    'max_lato' => (int) env('FOTO_MAX_LATO', 1600),

    // Qualità di ricompressione (1-100). Più basso = file più piccoli.
    // 80 è un ottimo compromesso tra peso e qualità.
    'qualita' => (int) env('FOTO_QUALITA', 80),

    // Formato di salvataggio:
    //   'webp' = più leggero (consigliato). Se il server non supporta WebP,
    //            si passa automaticamente a JPEG.
    //   'jpeg' = massima compatibilità.
    'formato' => env('FOTO_FORMATO', 'webp'),

];
