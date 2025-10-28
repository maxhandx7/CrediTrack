<?php

namespace App\Http\Controllers;

use App\Models\LoanSchedule;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanScheduleController extends Controller
{
    public function index()
    {
        $schedules = LoanSchedule::whereHas('loan', function ($q) {
            $q->where('user_id', Auth::id());
        })->with('loan.client')->orderBy('scheduled_date')->get();

        return response()->json($schedules);
    }

    public function store(Request $request)
    {
        $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'scheduled_date' => 'required|date',
            'amount_due' => 'required|numeric|min:0',
        ]);

    
        $loan = Loan::where('user_id', Auth::id())->findOrFail($request->loan_id);

        $schedule = LoanSchedule::create([
            'loan_id' => $loan->id,
            'scheduled_date' => $request->scheduled_date,
            'amount_due' => $request->amount_due,
            'status' => 'pendiente',
        ]);

        return response()->json([
            'message' => 'Cuota programada correctamente',
            'schedule' => $schedule,
        ], 201);
    }

    public function show($id)
    {
        $schedule = LoanSchedule::whereHas('loan', function ($q) {
            $q->where('user_id', Auth::id());
        })->with('loan.client')->findOrFail($id);

        return response()->json($schedule);
    }

    public function update(Request $request, $id)
    {
        $schedule = LoanSchedule::whereHas('loan', function ($q) {
            $q->where('user_id', Auth::id());
        })->findOrFail($id);

        $request->validate([
            'scheduled_date' => 'nullable|date',
            'amount_due' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:pendiente,pagado,atrasado',
        ]);

        $schedule->update($request->only(['scheduled_date', 'amount_due', 'status']));

        return response()->json([
            'message' => 'Cronograma actualizado correctamente',
            'schedule' => $schedule,
        ]);
    }

    public function destroy($id)
    {
        $schedule = LoanSchedule::whereHas('loan', function ($q) {
            $q->where('user_id', Auth::id());
        })->findOrFail($id);

        $schedule->delete();

        return response()->json(['message' => 'Cuota eliminada correctamente']);
    }
}
