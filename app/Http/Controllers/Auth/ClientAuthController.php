<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientLoginCode;
use App\Notifications\ClientAccessCode;
use App\Services\Waha\WahaClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Acceso de clientes en dos pasos: cédula → código de 6 dígitos por WhatsApp.
 * Antes bastaba con escribir una cédula para entrar.
 */
class ClientAuthController extends Controller
{
    public function requestCode(Request $request, WahaClient $waha)
    {
        $request->validate(['document' => 'required|string|max:20']);

        abort_unless($waha->enabled(), 503, 'El acceso de clientes no está disponible en este momento.');

        // La misma cédula puede existir con varios prestamistas: se envía a cada uno con WhatsApp.
        Client::where('document', trim($request->document))
            ->whereNotNull('phone')
            ->get()
            ->each(function (Client $client) {
                $client->loginCodes()->whereNull('consumed_at')->delete();

                $code = (string) random_int(100000, 999999);
                $client->loginCodes()->create([
                    'code_hash' => Hash::make($code),
                    'expires_at' => now()->addMinutes(config('creditrack.login_code_minutes')),
                ]);

                try {
                    $client->notifyNow(new ClientAccessCode($code));
                } catch (Throwable $e) {
                    report($e);
                }
            });

        // Respuesta idéntica exista o no la cédula: no se revela quién es cliente.
        return response()->json([
            'message' => 'Si la cédula está registrada, te enviamos un código por WhatsApp al número que tiene tu prestamista.',
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'document' => 'required|string|max:20',
            'code' => 'required|digits:6',
        ]);

        $clients = Client::where('document', trim($request->document))->get();

        foreach ($clients as $client) {
            $login = $client->loginCodes()->latest('id')->first();

            if (! $login?->isUsable()) {
                continue;
            }

            if (Hash::check($request->code, $login->code_hash)) {
                $login->forceFill(['consumed_at' => now()])->save();
                $client->tokens()->delete();

                return response()->json([
                    'token' => $client->createToken('client_token', ['client'])->plainTextToken,
                    'client' => $client->only(['id', 'name', 'document']),
                ]);
            }

            $login->increment('attempts');
        }

        return response()->json(['message' => 'Código incorrecto o vencido. Pide uno nuevo.'], 422);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}
