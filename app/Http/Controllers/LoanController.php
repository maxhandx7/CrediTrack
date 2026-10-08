<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\LoanLedger;
use App\Services\ScheduleGenerator;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    private const FINANCIAL_FIELDS = ['amount', 'interest_rate', 'interest_type', 'start_date', 'due_date', 'payment_frequency'];

    public function __construct(
        private ScheduleGenerator $generator,
        private LoanLedger $ledger,
        private WebhookDispatcher $webhooks,
    ) {}

    public function index(Request $request)
    {
        // Antes: 2 consultas extra POR préstamo. Ahora: 4 en total.
        $loans = $request->user()->loans()->with(['client', 'payments', 'schedules'])->latest()->get();
        $loans->each(fn (Loan $loan) => $loan->setAttribute('quotasCount', $loan->schedules->count()));

        return response()->json($loans);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));

        $loan = DB::transaction(function () use ($request, $data) {
            $loan = $request->user()->loans()->create($data + ['status' => 'activo']);
            $this->generator->generate($loan);

            return $this->ledger->recalculate($loan);
        });

        $this->webhooks->loanCreated($loan);

        return response()->json(['message' => 'Préstamo registrado correctamente', 'loan' => $loan->load('client')], 201);
    }

    public function show(Request $request, int $id)
    {
        $loan = $request->user()->loans()->with(['client', 'payments', 'schedules'])->findOrFail($id);
        $loan->setAttribute('schedulesCount', $loan->schedules->count());

        return response()->json($loan);
    }

    public function update(Request $request, int $id)
    {
        $loan = $request->user()->loans()->findOrFail($id);
        $data = $request->validate($this->rules($request, partial: true) + [
            'status' => 'sometimes|in:activo,cancelado,pagado,atrasado',
        ]);

        $changesMoney = collect(self::FINANCIAL_FIELDS)
            ->contains(fn ($f) => array_key_exists($f, $data) && (string) $data[$f] !== (string) $loan->getRawOriginal($f));

        if ($changesMoney && $loan->payments()->exists()) {
            return response()->json([
                'message' => 'Este préstamo ya tiene pagos: no se pueden cambiar monto, tasa, fechas ni frecuencia. Cancélalo y crea uno nuevo.',
            ], 422);
        }

        // Solo "cancelado" es una decisión manual; los demás estados los calcula el libro contable.
        if (isset($data['status']) && $data['status'] !== 'cancelado') {
            $data['status'] = $loan->status === \App\Enums\LoanStatus::Cancelled ? 'activo' : $loan->status->value;
        }

        DB::transaction(function () use ($loan, $data, $changesMoney) {
            $loan->update($data);
            if ($changesMoney) {
                $this->generator->generate($loan);
            }
            $this->ledger->recalculate($loan);
        });

        return response()->json(['message' => 'Préstamo actualizado correctamente', 'loan' => $loan->fresh(['client', 'schedules'])]);
    }

    public function destroy(Request $request, int $id)
    {
        $loan = $request->user()->loans()->findOrFail($id);
        $loan->delete();
        $this->webhooks->loanDeleted($loan);

        return response()->json(['message' => 'Préstamo eliminado correctamente']);
    }

    private function rules(Request $request, bool $partial = false): array
    {
        $r = $partial ? 'sometimes' : 'required';

        return [
            'client_id' => [$r, 'integer', fn ($attr, $value, $fail) => $request->user()->clients()->whereKey($value)->exists() ?: $fail('El cliente no existe.')],
            'amount' => "$r|numeric|min:1",
            'interest_rate' => "$r|numeric|min:0|max:100",
            'interest_type' => "$r|in:simple,compuesto",
            'start_date' => "$r|date",
            'due_date' => "$r|date|after_or_equal:start_date",
            'payment_frequency' => "$r|in:diaria,semanal,quincenal,mensual",
            'notes' => 'nullable|string',
        ];
    }
}
