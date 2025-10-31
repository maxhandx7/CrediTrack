<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Exporta un análisis completo del sistema para el usuario autenticado
     */
    public function export()
    {
        $userId = Auth::id();

        // Total de clientes
        $totalClients = Client::where('user_id', $userId)->count();

        // Total de préstamos
        $totalLoans = Loan::where('user_id', $userId)->count();

        // Préstamos activos y pagados
        $activeLoans = Loan::where('user_id', $userId)->where('status', 'activo')->count();
        $paidLoans = Loan::where('user_id', $userId)->where('status', 'pagado')->count();

        // Total prestado y total pagado
        $totalAmount = Loan::where('user_id', $userId)->sum('amount');
        $totalPaid = Payment::where('user_id', $userId)->sum('amount');
        $totalRemaining = $totalAmount - $totalPaid;

        // Pagos atrasados
        $overduePayments = LoanSchedule::whereHas('loan', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->where('status', 'atrasado')->count();

        // Clientes más activos (con más préstamos)
        $topClients = Client::where('user_id', $userId)
            ->withCount('loans')
            ->orderByDesc('loans_count')
            ->take(5)
            ->get();

        // Resumen de pagos por frecuencia
        $paymentFrequencySummary = Loan::where('user_id', $userId)
            ->select('payment_frequency', DB::raw('count(*) as total'))
            ->groupBy('payment_frequency')
            ->get();

        // Resumen de intereses generados (simple y compuesto)
        $interestSummary = Loan::where('user_id', $userId)
            ->get()
            ->map(function($loan) {
                $rate = $loan->interest_rate / 100;
                $principal = $loan->amount;
                $periods = match($loan->payment_frequency) {
                    'diaria' => now()->diffInDays($loan->due_date),
                    'semanal' => ceil(now()->diffInWeeks($loan->due_date)),
                    'quincenal' => ceil(now()->diffInDays($loan->due_date) / 15),
                    'mensual' => ceil(now()->diffInMonths($loan->due_date)),
                    default => 1,
                };

                if ($loan->interest_type === 'simple') {
                    $totalInterest = $principal * $rate * $periods;
                } else {
                    $totalInterest = $principal * pow(1 + $rate, $periods) - $principal;
                }

                return [
                    'loan_id' => $loan->id,
                    'client' => $loan->client->name ?? 'Cliente desconocido',
                    'interest_type' => $loan->interest_type,
                    'interest_amount' => round($totalInterest, 2)
                ];
            });

        // Armar el reporte completo
        $report = [
            'totals' => [
                'clients' => $totalClients,
                'loans' => $totalLoans,
                'active_loans' => $activeLoans,
                'paid_loans' => $paidLoans,
                'total_amount' => $totalAmount,
                'total_paid' => $totalPaid,
                'total_remaining' => $totalRemaining,
                'overdue_payments' => $overduePayments,
            ],
            'top_clients' => $topClients,
            'payment_frequency_summary' => $paymentFrequencySummary,
            'interest_summary' => $interestSummary,
        ];

        return response()->json($report);
    }
}
