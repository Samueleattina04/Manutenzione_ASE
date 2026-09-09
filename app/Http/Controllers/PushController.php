<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Support\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    /** Chiave pubblica VAPID + stato (per il browser). */
    public function key(): JsonResponse
    {
        return response()->json([
            'enabled' => WebPush::enabled(),
            'key' => WebPush::publicKey(),
        ]);
    }

    /** Registra (o aggiorna) l'iscrizione push del dispositivo corrente. */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $reparto = $user->isOperatore() ? (string) $request->session()->get('op_reparto') : null;

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'user_id' => $user->id,
                'reparto' => $reparto ?: null,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
            ]
        );

        return response()->json(['ok' => true]);
    }

    /** Rimuove l'iscrizione del dispositivo corrente. */
    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');
        if ($endpoint !== '') {
            PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->delete();
        }

        return response()->json(['ok' => true]);
    }
}
