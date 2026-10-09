@php($m = fn ($v) => '$'.number_format((float) $v, 0, ',', '.'))
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Recibo {{ $payment->receiptLabel() }}</title>@include('pdf._style')</head>
<body>
<div class="footer">{{ $lender->displayName() }}@if ($lender->phone) · {{ $lender->phone }}@endif · Generado por CrediTrack</div>
<table><tr>
    <td><p class="brand">{{ $lender->displayName() }}</p>@if ($lender->phone)<p class="muted" style="margin:0">{{ $lender->phone }}</p>@endif</td>
    <td><p class="title">RECIBO DE PAGO</p><p class="number">{{ $payment->receiptLabel() }}</p></td>
</tr></table>
<div class="rule"></div>

@if ($payment->isVoided())
    <p class="bad" style="font-size:13px;margin:0 0 12px">ANULADO el {{ $payment->voided_at->format('d/m/Y') }}: {{ $payment->void_reason }}</p>
@endif

<table><tr>
    <td style="width:55%;vertical-align:top;padding-right:12px">
        <p class="label">Recibimos de</p>
        <p style="font-size:13px;font-weight:bold;margin:0">{{ $loan->client->name }}</p>
        @if ($loan->client->document)<p class="muted" style="margin:0">C.C. {{ $loan->client->document }}</p>@endif
        <p class="label" style="margin-top:12px">Fecha</p>
        <p style="margin:0">{{ $payment->date->translatedFormat('j \d\e F \d\e Y') }}</p>
        <p class="label" style="margin-top:12px">Medio de pago</p>
        <p style="margin:0">{{ $payment->method->getLabel() }}@if ($payment->reference) · Ref. {{ $payment->reference }}@endif</p>
    </td>
    <td style="vertical-align:top">
        <div class="hl">
            <p class="label">Valor recibido</p>
            <p class="big">{{ $m($payment->amount) }}</p>
        </div>
        <div class="card" style="margin-top:10px">
            <p class="label">Saldo pendiente después de este pago</p>
            <p style="font-size:14px;font-weight:bold;margin:0">{{ $m($payment->remaining_balance) }}</p>
        </div>
    </td>
</tr></table>

<p class="label" style="margin-top:18px">Préstamo</p>
<table class="grid"><thead><tr><th>Préstamo</th><th class="r">Capital</th><th class="r">Total a pagar</th><th class="r">Pagado a la fecha</th><th class="r">Saldo actual</th></tr></thead>
<tbody><tr>
    <td>#{{ $loan->id }} · desde {{ $loan->start_date->format('d/m/Y') }}</td>
    <td class="r">{{ $m($loan->amount) }}</td><td class="r">{{ $m($loan->total_amount) }}</td>
    <td class="r">{{ $m($loan->total_paid) }}</td><td class="r ok">{{ $m($loan->balance) }}</td>
</tr></tbody></table>

@if ($payment->notes)<p class="label" style="margin-top:16px">Observaciones</p><p style="margin:0">{{ $payment->notes }}</p>@endif

<p class="muted" style="margin-top:28px;font-size:9px">Este recibo se generó electrónicamente y es válido sin firma. Verifícalo en el portal de clientes con tu cédula.</p>
</body></html>
