<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;

/** Recibos de pago y estados de cuenta en PDF. */
class Documents
{
    public function receipt(Payment $payment): string
    {
        $payment->loadMissing('loan.client', 'loan.user');

        return $this->render('pdf.receipt', ['payment' => $payment, 'loan' => $payment->loan, 'lender' => $payment->loan->user]);
    }

    public function statement(Loan $loan): string
    {
        $loan->loadMissing(['client', 'user', 'schedules', 'payments' => fn ($q) => $q->orderBy('date')]);

        return $this->render('pdf.statement', ['loan' => $loan, 'lender' => $loan->user]);
    }

    private function render(string $view, array $data): string
    {
        return Pdf::loadView($view, $data)->setPaper('letter')->setOption(['isRemoteEnabled' => false])->output();
    }
}
