@extends('layouts.portal')
@section('title', 'Software para prestamistas')
@php($m = fn ($v) => '$'.number_format((float) $v, 0, ',', '.'))
@section('body')
<section class="hero" style="min-height:auto;padding:72px 24px 88px">
    <div>
        <span class="logo" style="justify-content:center"><span class="logo-mark">C</span> CrediTrack</span>
        <h1>Tu cartera de préstamos, cobrada a tiempo.</h1>
        <p>Cuotas, pagos, mora y recordatorios automáticos por WhatsApp. Tus clientes consultan su préstamo desde el celular, y tú ves en un vistazo quién te debe.</p>
        <div class="choices">
            <a class="btn" href="{{ url('/admin/register') }}">Pruébalo gratis {{ config('saas.trial_days') }} días</a>
            <a class="btn btn-ghost" href="{{ url('/admin/login') }}">Ya tengo cuenta</a>
            <a class="btn btn-ghost" href="{{ route('portal.login') }}" style="border:0;font-weight:500">Soy cliente: ver mi préstamo →</a>
        </div>
    </div>
</section>

<main class="wrap" style="max-width:980px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:-48px 0 40px">
        @foreach ([
            ['Recordatorios solos', 'La víspera, el día del pago y cuando hay atraso. Tú decides qué se envía y a qué hora.'],
            ['Bandeja de cobro', 'Cada mañana sabes a quién cobrarle, cuánto y con un toque registras el pago.'],
            ['Promesas y reestructuración', '“Le pago el viernes” queda registrado y se le recuerda. Reorganiza cuotas sin perder el historial.'],
            ['Tu equipo', 'Agrega cobradores que registran pagos sin poder modificar ni borrar nada.'],
        ] as [$t, $d])
            <div class="card" style="margin:0"><strong style="display:block;margin-bottom:6px">{{ $t }}</strong><span style="color:var(--muted);font-size:.95rem">{{ $d }}</span></div>
        @endforeach
    </div>

    <h2 style="text-align:center;font-size:1.7rem;margin:0 0 6px">Planes</h2>
    <p class="lead" style="text-align:center">Sin permanencia. Empieza con {{ config('saas.trial_days') }} días gratis con todo incluido.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:48px">
        @foreach ($plans as $plan)
            <div class="card" style="margin:0;{{ $plan->slug === 'profesional' ? 'border:2px solid var(--brand)' : '' }}">
                <strong style="font-size:1.15rem">{{ $plan->name }}</strong>
                <p style="font-size:1.9rem;font-weight:800;margin:6px 0 2px">{{ $m($plan->price_monthly) }}<span style="font-size:.9rem;font-weight:500;color:var(--muted)"> / mes</span></p>
                <ul style="padding-left:18px;color:var(--ink-2);line-height:1.8;margin:10px 0 0">
                    <li>{{ $plan->max_active_loans ? "Hasta {$plan->max_active_loans} préstamos activos" : 'Préstamos ilimitados' }}</li>
                    <li>{{ $plan->max_users ? "Hasta {$plan->max_users} personas" : 'Equipo ilimitado' }}</li>
                    <li>Recordatorios por WhatsApp</li>
                    <li>Portal para tus clientes</li>
                    @if ($plan->hasFeature('branding'))<li>Tu logo en el portal y los PDF</li>@endif
                    @if ($plan->hasFeature('webhook'))<li>Integraciones (webhook)</li>@endif
                    @if ($plan->hasFeature('own_whatsapp'))<li>Tu propio número de WhatsApp</li>@endif
                </ul>
                <a class="btn {{ $plan->slug === 'profesional' ? '' : 'btn-ghost' }}" href="{{ url('/admin/register') }}">Empezar gratis</a>
            </div>
        @endforeach
    </div>
</main>
<p class="foot">CrediTrack · Hecho en Cali, Colombia</p>
@endsection
