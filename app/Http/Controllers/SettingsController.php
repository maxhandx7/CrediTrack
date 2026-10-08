<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Preferencias del prestamista: avisos por WhatsApp y webhook (ej. hacia afdeveloper.com). */
class SettingsController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'notifications_enabled' => $user->notifications_enabled,
            'phone' => $user->phone,
            'webhook_url' => $user->webhook_url,
            'webhook_configured' => filled($user->webhook_secret),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'notifications_enabled' => 'sometimes|boolean',
            'phone' => 'sometimes|nullable|string|max:20',
            'webhook_url' => 'sometimes|nullable|url:https|max:255',
            'rotate_webhook_secret' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $secret = null;

        if (($data['rotate_webhook_secret'] ?? false) || (filled($data['webhook_url'] ?? null) && blank($user->webhook_secret))) {
            $secret = Str::random(48);
            $user->webhook_secret = $secret;
        }

        $user->fill(collect($data)->except('rotate_webhook_secret')->all())->save();

        return response()->json(array_filter([
            'message' => 'Configuración guardada',
            // Se muestra UNA sola vez: cópialo en el sistema que recibe el webhook.
            'webhook_secret' => $secret,
        ]));
    }
}
