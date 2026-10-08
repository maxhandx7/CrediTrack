<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Lo que ve el deudor: solo SUS préstamos, cuotas y pagos. */
class ClientPortalController extends Controller
{
    public function me(Request $request)
    {
        return response()->json($request->user()->only(['id', 'name', 'document', 'phone', 'email']));
    }

    public function loans(Request $request)
    {
        return response()->json(
            $request->user()->loans()
                ->with(['schedules', 'payments' => fn ($q) => $q->orderByDesc('date'), 'user:id,name,phone'])
                ->latest()
                ->get()
        );
    }
}
