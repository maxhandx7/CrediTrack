<?php

namespace App\Http\Controllers;

use App\Models\LoanSchedule;
use App\Services\LoanLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Las cuotas las genera el sistema y su estado lo decide el libro contable
 * (según los pagos). Aquí solo se consultan y se puede reprogramar una fecha.
 */
class LoanScheduleController extends Controller
{
    public function __construct(private LoanLedger $ledger) {}

    private function query(Request $request)
    {
        return LoanSchedule::whereHas('loan', fn ($q) => $q->where('user_id', $request->user()->id));
    }

    public function index(Request $request)
    {
        return response()->json($this->query($request)->with('loan.client')->orderBy('scheduled_date')->get());
    }

    public function show(Request $request, int $id)
    {
        return response()->json($this->query($request)->with('loan.client')->findOrFail($id));
    }

    /** Cargo adicional (ej. mora): agrega una cuota y suma su valor al total del préstamo. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'loan_id' => 'required|integer',
            'scheduled_date' => 'required|date',
            'amount_due' => 'required|numeric|min:1',
        ]);
        $loan = $request->user()->loans()->findOrFail($data['loan_id']);

        $schedule = DB::transaction(function () use ($loan, $data) {
            $schedule = $loan->schedules()->create(['scheduled_date' => $data['scheduled_date'], 'amount_due' => $data['amount_due']]);
            $loan->forceFill([
                'total_amount' => round((float) $loan->total_amount + (float) $data['amount_due'], 2),
                'installments_count' => $loan->installments_count + 1,
            ])->save();
            $this->ledger->recalculate($loan);

            return $schedule->fresh();
        });

        return response()->json(['message' => 'Cargo agregado al préstamo', 'schedule' => $schedule], 201);
    }

    /**
     * Solo se reprograma la fecha. El estado lo decide el libro contable según los pagos;
     * si el frontend viejo manda "status" se ignora en vez de fallar.
     */
    public function update(Request $request, int $id)
    {
        $schedule = $this->query($request)->findOrFail($id);
        $data = $request->validate(['scheduled_date' => 'sometimes|date']);

        if ($data !== []) {
            $schedule->update($data);
            $this->ledger->recalculate($schedule->loan);
        }

        return response()->json(['message' => 'Cuota actualizada correctamente', 'schedule' => $schedule->fresh()]);
    }

    public function destroy(Request $request, int $id)
    {
        $schedule = $this->query($request)->findOrFail($id);

        if ((float) $schedule->amount_paid > 0) {
            return response()->json(['message' => 'La cuota ya tiene abonos y no se puede eliminar.'], 422);
        }

        DB::transaction(function () use ($schedule) {
            $loan = $schedule->loan;
            $schedule->delete();
            $loan->forceFill([
                'total_amount' => max(0, round((float) $loan->total_amount - (float) $schedule->amount_due, 2)),
                'installments_count' => max(0, $loan->installments_count - 1),
            ])->save();
            $this->ledger->recalculate($loan);
        });

        return response()->json(['message' => 'Cuota eliminada correctamente']);
    }
}
