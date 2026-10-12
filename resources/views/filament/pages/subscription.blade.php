@php($m = fn ($v) => '$'.number_format((float) $v, 0, ',', '.'))
<x-filament-panels::page>
    @unless ($company->hasAccess())
        <x-filament::section>
            <div style="color: var(--danger-600, #c2410c); font-weight: 700; font-size: 1.1rem">
                {{ $company->status === 'trial' ? 'Tu prueba gratis terminó.' : 'Tu suscripción está vencida.' }}
            </div>
            <p style="margin:.4rem 0 0">Tus datos están a salvo. Para seguir usando CrediTrack, renueva tu plan con las instrucciones de abajo.</p>
        </x-filament::section>
    @endunless

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem">
        <x-filament::section>
            <div style="font-size:.85rem;opacity:.7">Estado</div>
            <div style="margin-top:.35rem"><x-filament::badge :color="$company->statusColor()">{{ $company->statusLabel() }}</x-filament::badge></div>
            @if (($left = $company->daysLeft()) !== null)
                <div style="font-size:.9rem;margin-top:.5rem">{{ $left >= 0 ? "Quedan {$left} día(s)" : 'Venció hace '.abs($left).' día(s)' }}
                    · {{ ($company->status === 'trial' ? $company->trial_ends_at : $company->paid_until)?->translatedFormat('j M Y') }}</div>
            @endif
        </x-filament::section>
        <x-filament::section>
            <div style="font-size:.85rem;opacity:.7">Plan</div>
            <div style="font-size:1.3rem;font-weight:700;margin-top:.15rem">{{ $company->plan?->name ?? '—' }}</div>
            <div style="font-size:.9rem;opacity:.75">{{ $company->plan ? $m($company->plan->price_monthly).' / mes' : '' }}</div>
        </x-filament::section>
        <x-filament::section>
            <div style="font-size:.85rem;opacity:.7">Uso</div>
            <div style="margin-top:.25rem">{{ $usage['loans'] }} / {{ $company->plan?->max_active_loans ?? '∞' }} préstamos activos</div>
            <div>{{ $usage['users'] }} / {{ $company->plan?->max_users ?? '∞' }} personas en el equipo</div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Cómo pagar">
        <p style="white-space:pre-line;margin:0">{{ $instructions }}</p>
        <p style="margin:.75rem 0 0">
            <x-filament::button tag="a" target="_blank" color="success"
                :href="'https://wa.me/'.$support.'?text='.rawurlencode('Hola, quiero renovar CrediTrack para '.$company->name.'. Te envío el comprobante.')">
                Enviar comprobante por WhatsApp
            </x-filament::button>
        </p>
    </x-filament::section>

    <x-filament::section heading="Planes">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
            @foreach ($plans as $plan)
                <div style="border:1px solid rgba(127,127,127,.25);border-radius:.75rem;padding:1rem;{{ $plan->id === $company->plan_id ? 'outline:2px solid rgb(var(--primary-600, 15 118 110));' : '' }}">
                    <div style="font-weight:700;font-size:1.1rem">{{ $plan->name }} @if ($plan->id === $company->plan_id)<x-filament::badge size="sm">Tu plan</x-filament::badge>@endif</div>
                    <div style="font-size:1.4rem;font-weight:800;margin:.25rem 0">{{ $m($plan->price_monthly) }}<span style="font-size:.85rem;font-weight:400;opacity:.7"> / mes</span></div>
                    <ul style="margin:.25rem 0 0;padding-left:1.1rem;font-size:.92rem;line-height:1.7">
                        <li>{{ $plan->max_active_loans ? "Hasta {$plan->max_active_loans} préstamos activos" : 'Préstamos ilimitados' }}</li>
                        <li>{{ $plan->max_users ? "Hasta {$plan->max_users} personas" : 'Equipo ilimitado' }}</li>
                        <li>{{ $plan->max_daily_messages ?? config('saas.default_daily_messages') }} WhatsApp automáticos al día</li>
                        @if ($plan->hasFeature('branding'))<li>Tu logo en el portal y los PDF</li>@endif
                        @if ($plan->hasFeature('webhook'))<li>Integración por webhook</li>@endif
                        @if ($plan->hasFeature('own_whatsapp'))<li>Tu propio número de WhatsApp</li>@endif
                    </ul>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    @if ($company->subscriptionPayments->isNotEmpty())
        <x-filament::section heading="Pagos registrados">
            <table style="width:100%;font-size:.95rem">
                @foreach ($company->subscriptionPayments as $p)
                    <tr style="border-top:1px solid rgba(127,127,127,.15)">
                        <td style="padding:.45rem 0">{{ $p->created_at->format('d/m/Y') }}</td>
                        <td>{{ $p->months }} mes(es) · hasta {{ $p->period_end->format('d/m/Y') }}</td>
                        <td style="text-align:right;font-weight:600">{{ $m($p->amount) }}</td>
                    </tr>
                @endforeach
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
