@php($m = fn ($v) => '$'.number_format((float) $v, 0, ',', '.'))
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Estado de cuenta</title>@include('pdf._style')</head>
<body>
<div class="footer">{{ $lender->displayName() }}@if ($lender->phone) · {{ $lender->phone }}@endif · Estado de cuenta al {{ now()->format('d/m/Y') }}</div>
<table><tr>
    <td><p class="brand">{{ $lender->displayName() }}</p></td>
    <td><p class="title">ESTADO DE CUENTA</p><p class="number">Préstamo #{{ $loan->id }}</p><p class="muted" style="text-align:right;margin:0">al {{ now()->translatedFormat('j \d\e F \d\e Y') }}</p></td>
</tr></table>
<div class="rule"></div>

<table><tr>
    <td style="width:40%;vertical-align:top;padding-right:12px">
        <p class="label">Cliente</p>
        <p style="font-size:13px;font-weight:bold;margin:0">{{ $loan->client->name }}</p>
        @if ($loan->client->document)<p class="muted" style="margin:0">C.C. {{ $loan->client->document }}</p>@endif
        <p class="label" style="margin-top:10px">Condiciones</p>
        <p style="margin:0">{{ $m($loan->amount) }} al {{ rtrim(rtrim(number_format($loan->interest_rate, 2, ',', '.'), '0'), ',') }}% {{ mb_strtolower($loan->interest_type->getLabel()) }}, pago {{ mb_strtolower($loan->payment_frequency->getLabel()) }}</p>
        <p class="muted" style="margin:0">{{ $loan->start_date->format('d/m/Y') }} → {{ $loan->due_date->format('d/m/Y') }}</p>
    </td>
    <td style="vertical-align:top">
        <table><tr>
            <td class="card" style="width:33%"><p class="label">Total</p><p style="font-size:13px;font-weight:bold;margin:0">{{ $m($loan->total_amount) }}</p></td>
            <td style="width:6px"></td>
            <td class="card" style="width:33%"><p class="label">Pagado</p><p class="ok" style="font-size:13px;margin:0">{{ $m($loan->total_paid) }}</p></td>
            <td style="width:6px"></td>
            <td class="hl"><p class="label">Saldo</p><p style="font-size:15px;font-weight:bold;margin:0">{{ $m($loan->balance) }}</p></td>
        </tr></table>
        @if (($overdue = $loan->overdueAmount()) > 0)
            <p class="bad" style="margin:8px 0 0">En mora: {{ $m($overdue) }}</p>
        @endif
    </td>
</tr></table>

<p class="label" style="margin-top:18px">Cuotas</p>
<table class="grid"><thead><tr><th>Fecha</th><th>Concepto</th><th class="r">Valor</th><th class="r">Abonado</th><th class="r">Pendiente</th><th>Estado</th></tr></thead><tbody>
@foreach ($loan->schedules as $s)
    <tr>
        <td>{{ $s->scheduled_date->format('d/m/Y') }}</td>
        <td>{{ $s->isFee() ? 'Recargo por mora' : 'Cuota' }}</td>
        <td class="r">{{ $m($s->amount_due) }}</td>
        <td class="r">{{ $m($s->amount_paid) }}</td>
        <td class="r">{{ $m($s->amount_pending) }}</td>
        <td class="{{ $s->status->value === 'pagado' ? 'ok' : ($s->status->value === 'vencido' ? 'bad' : '') }}">{{ $s->status->getLabel() }}</td>
    </tr>
@endforeach
</tbody></table>

@if ($loan->payments->isNotEmpty())
<p class="label" style="margin-top:18px">Pagos recibidos</p>
<table class="grid"><thead><tr><th>Recibo</th><th>Fecha</th><th>Medio</th><th class="r">Valor</th></tr></thead><tbody>
@foreach ($loan->payments as $p)
    <tr class="{{ $p->isVoided() ? 'void' : '' }}">
        <td>{{ $p->receiptLabel() }}{{ $p->isVoided() ? ' (anulado)' : '' }}</td><td>{{ $p->date->format('d/m/Y') }}</td>
        <td>{{ $p->method->getLabel() }}</td><td class="r">{{ $m($p->amount) }}</td>
    </tr>
@endforeach
</tbody></table>
@endif
</body></html>
