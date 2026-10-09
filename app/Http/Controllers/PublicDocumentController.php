<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Payment;
use App\Services\Documents;

/** Enlaces que reciben los clientes por WhatsApp (token imposible de adivinar). */
class PublicDocumentController extends Controller
{
    public function receipt(string $token, Documents $docs)
    {
        $payment = Payment::where('receipt_token', $token)->firstOrFail();

        return $this->pdf($docs->receipt($payment), 'recibo-'.$payment->receiptLabel().'.pdf');
    }

    public function statement(string $token, Documents $docs)
    {
        $loan = Loan::where('statement_token', $token)->firstOrFail();

        return $this->pdf($docs->statement($loan), 'estado-de-cuenta-'.$loan->id.'.pdf');
    }

    private function pdf(string $content, string $name)
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
