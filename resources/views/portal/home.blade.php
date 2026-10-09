@extends('layouts.portal')
@section('title', 'Mis préstamos')
@php($m = fn ($v) => '$'.number_format((float) $v, 0, ',', '.'))
@section('body')
<header class="top">
    <div class="wrap">
        <span class="logo"><span class="logo-mark">C</span> CrediTrack</span>
        <form method="post" action="{{ route('portal.logout') }}">@csrf<button type="submit">Salir</button></form>
    </div>
</header>

<main class="wrap">
    <h1>Hola, {{ $client->firstName() }}</h1>
    <p class="lead">{{ $loans->count() === 1 ? 'Este es tu préstamo.' : 'Estos son tus préstamos.' }}</p>

    @forelse ($loans as $loan)
        @php($next = $loan->nextSchedule())
        <section class="card">
            <div class="loan-head">
                <div>
                    <small>Préstamo con {{ $loan->user->displayName() }}</small>
                    <p class="balance">{{ $m($loan->balance) }}</p>
                    <small>por pagar de {{ $m($loan->total_amount) }}</small>
                </div>
                <span class="pill pill-{{ $loan->status->value }}">{{ $loan->status->getLabel() }}</span>
            </div>

            <div class="bar"><span style="width: {{ $loan->progress() }}%"></span></div>
            <div class="bar-label"><span>Pagado {{ $m($loan->total_paid) }}</span><span>{{ $loan->progress() }}%</span></div>

            @if ($next)
                <div class="next {{ $next->status->value === 'vencido' ? 'late' : '' }}">
                    {{ $next->status->value === 'vencido' ? 'Cuota vencida' : 'Próxima cuota' }}:
                    <strong>{{ $m($next->amount_pending) }}</strong>
                    · {{ $next->scheduled_date->translatedFormat('j \d\e F') }}
                    @if (($overdue = $loan->overdueAmount()) > $next->amount_pending)
                        <br><small>Total vencido: {{ $m($overdue) }}</small>
                    @endif
                </div>
            @elseif ($loan->status->value === 'pagado')
                <div class="next">🎉 ¡Pagaste todo este préstamo! Gracias por tu cumplimiento.</div>
            @endif

            <div class="actions">
                <a class="btn btn-ghost" href="{{ $loan->statementUrl() }}" target="_blank">Estado de cuenta</a>
                @if ($phone = preg_replace('/\D/', '', (string) $loan->user->phone))
                    <a class="btn" href="https://wa.me/{{ strlen($phone) === 10 ? '57'.$phone : $phone }}?text={{ rawurlencode('Hola, soy '.$client->name.'. Te escribo por mi préstamo.') }}" target="_blank" rel="noopener">Escribir</a>
                @endif
            </div>

            <details>
                <summary>Cuotas ({{ $loan->schedules->count() }})</summary>
                <ul class="rows">
                    @foreach ($loan->schedules as $s)
                        <li>
                            <span>{{ $s->scheduled_date->translatedFormat('j M Y') }}<br><small>{{ $s->isFee() ? 'Recargo por mora' : 'Cuota' }}</small></span>
                            <span class="amt">{{ $m($s->amount_due) }}<br><small class="st-{{ $s->status->value }}">{{ $s->status->getLabel() }}@if ($s->status->value !== 'pagado' && $s->amount_paid > 0) · abonado {{ $m($s->amount_paid) }}@endif</small></span>
                        </li>
                    @endforeach
                </ul>
            </details>

            @if ($loan->payments->isNotEmpty())
                <details>
                    <summary>Pagos y recibos ({{ $loan->payments->whereNull('voided_at')->count() }})</summary>
                    <ul class="rows">
                        @foreach ($loan->payments->whereNull('voided_at') as $p)
                            <li>
                                <span>{{ $p->date->translatedFormat('j M Y') }}<br><small>{{ $p->method->getLabel() }} · {{ $p->receiptLabel() }}</small></span>
                                <span class="amt">{{ $m($p->amount) }}<br><small><a href="{{ $p->receiptUrl() }}" target="_blank">Ver recibo</a></small></span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </section>
    @empty
        <div class="card empty">No tienes préstamos registrados.</div>
    @endforelse
</main>
<p class="foot">CrediTrack · Tus datos solo los ves tú y tu prestamista.</p>
@endsection
