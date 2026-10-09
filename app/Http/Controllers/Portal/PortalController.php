<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientLoginCode;
use App\Notifications\ClientAccessCode;
use App\Services\Waha\WahaClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Throwable;

/** Portal del deudor: /mi-cuenta. Cédula → código por WhatsApp → sus préstamos. */
class PortalController extends Controller
{
    public function showLogin()
    {
        return Auth::guard('client')->check() ? redirect()->route('portal.home') : view('portal.login');
    }

    public function requestCode(Request $request, WahaClient $waha)
    {
        $data = $request->validate(['document' => 'required|string|max:20']);

        if (! $waha->enabled()) {
            return back()->withErrors(['document' => 'El acceso no está disponible en este momento. Escríbele a tu prestamista.']);
        }

        Client::where('document', trim($data['document']))->whereNotNull('phone')->get()->each(function (Client $client) {
            $client->loginCodes()->whereNull('consumed_at')->delete();
            $code = (string) random_int(100000, 999999);
            $client->loginCodes()->create(['code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(config('creditrack.login_code_minutes'))]);
            try {
                $client->notifyNow(new ClientAccessCode($code));
            } catch (Throwable $e) {
                report($e);
            }
        });

        // Misma respuesta exista o no la cédula: no se revela quién es cliente.
        return redirect()->route('portal.verify')->with('document', trim($data['document']));
    }

    public function showVerify(Request $request)
    {
        $document = session('document', old('document'));

        return $document ? view('portal.verify', ['document' => $document]) : redirect()->route('portal.login');
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['document' => 'required|string|max:20', 'code' => 'required|digits:6']);

        foreach (Client::where('document', trim($data['document']))->get() as $client) {
            /** @var ClientLoginCode|null $login */
            $login = $client->loginCodes()->latest('id')->first();
            if (! $login?->isUsable()) {
                continue;
            }
            if (Hash::check($data['code'], $login->code_hash)) {
                $login->forceFill(['consumed_at' => now()])->save();
                Auth::guard('client')->login($client);
                $request->session()->regenerate();

                return redirect()->route('portal.home');
            }
            $login->increment('attempts');
        }

        return back()->withInput()->with('document', $data['document'])->withErrors(['code' => 'Código incorrecto o vencido. Pide uno nuevo.']);
    }

    public function home()
    {
        $client = Auth::guard('client')->user();

        $loans = $client->loans()
            ->with(['user', 'schedules', 'payments' => fn ($q) => $q->orderByDesc('date')])
            ->orderByRaw("CASE status WHEN 'atrasado' THEN 0 WHEN 'activo' THEN 1 WHEN 'pagado' THEN 2 ELSE 3 END")
            ->get();

        return view('portal.home', ['client' => $client, 'loans' => $loans]);
    }

    public function logout(Request $request)
    {
        Auth::guard('client')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
