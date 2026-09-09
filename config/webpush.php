<?php

// Chiavi VAPID per le notifiche Web Push. Genera i valori con:
//   php artisan push:vapid
// e incollali nel .env. Senza queste chiavi le notifiche push restano
// semplicemente disattivate (l'app funziona comunque).

return [
    // Chiave pubblica VAPID (base64url del punto grezzo da 65 byte).
    'public_key' => env('WEBPUSH_PUBLIC_KEY', ''),

    // Chiave privata VAPID: PEM codificato in base64 (una riga).
    'private_key' => env('WEBPUSH_PRIVATE_KEY', ''),

    // Contatto del mittente (richiesto dallo standard VAPID).
    'subject' => env('WEBPUSH_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'manutenzionease@gmail.com')),
];
