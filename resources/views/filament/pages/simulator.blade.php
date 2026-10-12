@php($plan = $this->plan())
@php($m = fn ($v) => '$'.number_format((float) $v, 0, ',', '.'))
<x-filament-panels::page>
    {{ $this->form }}

    @if ($plan)
        @php($amount = (float) ($this->data['amount'] ?? 0))
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem">
            @foreach ([
                ['Cuotas', count($plan['installments']).' de '.$m($plan['installments'][0]['amount'])],
                ['Total a pagar', $m($plan['total'])],
                ['Ganancia', $m($plan['total'] - $amount)],
                ['Rentabilidad', $amount > 0 ? round(($plan['total'] - $amount) / $amount * 100, 1).'%' : '—'],
            ] as [$label, $value])
                <x-filament::section>
                    <div style="font-size:.85rem;opacity:.7">{{ $label }}</div>
                    <div style="font-size:1.4rem;font-weight:700;margin-top:.15rem">{{ $value }}</div>
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section heading="Plan de cuotas">
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:.95rem">
                    <thead><tr style="text-align:left;opacity:.7"><th style="padding:.5rem">#</th><th style="padding:.5rem">Fecha</th><th style="padding:.5rem;text-align:right">Cuota</th><th style="padding:.5rem;text-align:right">Acumulado</th></tr></thead>
                    <tbody>
                        @php($acc = 0)
                        @foreach ($plan['installments'] as $i => $row)
                            @php($acc += $row['amount'])
                            <tr style="border-top:1px solid rgba(127,127,127,.15)">
                                <td style="padding:.5rem">{{ $i + 1 }}</td>
                                <td style="padding:.5rem">{{ \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('D j \d\e M Y') }}</td>
                                <td style="padding:.5rem;text-align:right;font-weight:600">{{ $m($row['amount']) }}</td>
                                <td style="padding:.5rem;text-align:right;opacity:.7">{{ $m($acc) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>Completa los datos para ver el plan.</x-filament::section>
    @endif
</x-filament-panels::page>
