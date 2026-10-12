@extends('layouts.portal')
@section('title', 'Mis préstamos')
@php($m = fn ($v) => '$'.number_format((float) $v, 0, ',', '.'))
@section('body')
<header class="top">
    <div class="wrap">
        @php($brand = $client->company)
        <span class="logo">
            @if ($brand?->logo && $brand->hasFeature('branding'))<img src="{{ route('company.logo', $brand) }}" alt="" style="height:30px;border-radius:8px;background:#fff">@else<span class="logo-mark">{{ mb_substr($brand?->name ?? 'C', 0, 1) }}</span>@endif
            {{ $brand?->name ?? 'CrediTrack' }}
        </span>
        <form method="post" action="{{ route('portal.logout') }}">@csrf<button type="submit">Salir</button></form>
    </div>
</header>

<main class="wrap">
    <h1>Hola, {{ $client->firstName() }}</h1>
    <p class="lead">{{ $loans->count() === 1 ? 'Este es tu préstamo.' : 'Estos son tus préstamos.' }}</p>

    @forelse ($loans as $loan)
        @php($next = $loan->nextSchedule())
        @php($co = $loan->company)
        @php($showBalance = (bool) $co->setting('portal.show_balance'))
        <section class="card">
            <div class="loan-head">
                <div>
                    <small>Préstamo con {{ $co->displayName() }}</small>
                    @if ($showBalance)
                        <p class="balance">{{ $m($loan->balance) }}</p>
                        <small>por pagar de {{ $m($loan->total_amount) }}</small>
                    @elseif (($overdueNow = $loan->overdueAmount()) > 0)
                        {{-- Sin mostrar la deuda total: lo que necesita pagar para ponerse al día --}}
                        <p class="balance">{{ $m($overdueNow) }}</p>
                        <small>para ponerte al día (cuotas vencidas)</small>
                    @elseif ($next)
                        <p class="balance">{{ $m($next->amount_pending) }}</p>
                        <small>próxima cuota · {{ $next->scheduled_date->translatedFormat('j \d\e F') }}</small>
                    @endif
                </div>
                <span class="pill pill-{{ $loan->status->value }}">{{ $loan->status->getLabel() }}</span>
            </div>

            @if ($showBalance)
                <div class="bar"><span style="width: {{ $loan->progress() }}%"></span></div>
                <div class="bar-label"><span>Pagado {{ $m($loan->total_paid) }}</span><span>{{ $loan->progress() }}%</span></div>
            @endif

            @if ($next && $showBalance)
                <div class="next {{ $next->status->value === 'vencido' ? 'late' : '' }}">
                    {{ $next->status->value === 'vencido' ? 'Cuota vencida' : 'Próxima cuota' }}:
                    <strong>{{ $m($next->amount_pending) }}</strong>
                    · {{ $next->scheduled_date->translatedFormat('j \d\e F') }}
                    @if ($showBalance && ($overdue = $loan->overdueAmount()) > $next->amount_pending)
                        <br><small>Total vencido: {{ $m($overdue) }}</small>
                    @endif
                </div>
            @elseif ($loan->status->value === 'pagado')
                <div class="next">🎉 ¡Pagaste todo este préstamo! Gracias por tu cumplimiento.</div>
            @endif

            <div class="actions">
                @if ($showBalance && $co->setting('portal.show_statement'))
                    <a class="btn btn-ghost" href="{{ $loan->statementUrl() }}" target="_blank">Estado de cuenta</a>
                @endif
                @if ($co->setting('portal.show_contact') && ($phone = preg_replace('/\D/', '', (string) $co->phone)))
                    <a class="btn" href="https://wa.me/{{ strlen($phone) === 10 ? '57'.$phone : $phone }}?text={{ rawurlencode('Hola, soy '.$client->name.'. Te escribo por mi préstamo.') }}" target="_blank" rel="noopener">Escribir</a>
                @endif
            </div>

            @if ($co->setting('portal.show_schedule'))
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
            @endif

            @if ($co->setting('portal.show_payments') && $loan->payments->isNotEmpty())
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
<p class="foot">{{ $client->company?->name }} · con tecnología de CrediTrack</p>
@endsection