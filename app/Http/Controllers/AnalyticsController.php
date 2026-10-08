<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleStatus;
use App\Models\LoanSchedule;
use Illuminate\Http\Request;

/**
 * Resumen de la cartera. Mantiene las claves que usa el frontend y corrige los
 * cálculos: antes "pendiente" restaba pagos al capital sin intereses.
 */
class AnalyticsController extends Controller
{
    public function export(Request $request)
    {
        $user = $request->user();
        $loans = $user->loans()->with(['client', 'payments', 'schedules'])->get();

        $totalAmount = (float) $loans->sum('amount');
        $totalToCollect = (float) $loans->sum('total_amount');
        $totalPaid = (float) $loans->sum(fn ($l) => $l->total_paid);
        $overdue = LoanSchedule::whereIn('loan_id', $loans->pluck('id'))->where('status', ScheduleStatus::Overdue)->get();

        return response()->json([
            'summary' => [
                'total_clients' => $user->clients()->count(),
                'total_loans' => $loans->count(),
                'active_loans' => $loans->where('status.value', 'activo')->count(),
                'late_loans' => $loans->where('status.value', 'atrasado')->count(),
                'paid_loans' => $loans->where('status.value', 'pagado')->count(),
                'total_amount' => round($totalAmount, 2),
                'total_to_collect' => round($totalToCollect, 2),
                'total_paid' => round($totalPaid, 2),
                'pending_amount' => round(max(0, $totalToCollect - $totalPaid), 2),
                'expected_interest' => round($totalToCollect - $totalAmount, 2),
                'overdue_amount' => round((float) $overdue->sum(fn ($s) => $s->amount_pending), 2),
                'overdue_installments' => $overdue->count(),
            ],
            'payment_frequency_summary' => $loans->groupBy(fn ($l) => $l->payment_frequency->value)
                ->map(fn ($g, $k) => ['payment_frequency' => $k, 'total' => $g->count()])->values(),
            'interest_summary' => $loans->map(fn ($l) => [
                'loan_id' => $l->id,
                'client' => $l->client?->name,
                'interest_type' => $l->interest_type->value,
                'principal' => (float) $l->amount,
                'interest_amount' => round((float) $l->total_amount - (float) $l->amount, 2),
            ])->values(),
            'loans_detail' => $loans->map(fn ($l) => [
                'loan_id' => $l->id,
                'client' => $l->client?->name,
                'status' => $l->status->value,
                'total_amount' => (float) $l->total_amount,
                'total_paid' => $l->total_paid,
                'balance' => $l->balance,
            ])->values(),
        ]);
    }
}
