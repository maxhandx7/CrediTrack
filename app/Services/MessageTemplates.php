<?php

namespace App\Services;

use App\Models\Company;

/**
 * Textos de WhatsApp editables por cada empresa.
 * Variables: {nombre} {monto} {fecha} {saldo} {dias} {prestamista} {enlace}
 * Lo que va entre [[ ]] es opcional: se elimina si usa una variable que la empresa decidió ocultar
 * (ej. "[[Saldo: {saldo}.]]" desaparece si la empresa no muestra el saldo en los mensajes).
 */
class MessageTemplates
{
    public const DEFAULTS = [
        'reminder' => "Hola {nombre} 👋\n\nTe recuerdo que {fecha} vence tu cuota de *{monto}*.[[\nSaldo total del préstamo: {saldo}.]]\n\nSi ya pagaste, ignora este mensaje. ¡Gracias! 🙏\n— {prestamista}",
        'overdue' => "Hola {nombre}.\n\nTu cuota de *{monto}* que vencía {fecha} tiene {dias} día(s) de atraso.\n¿Me confirmas cuándo te pones al día? Si ya pagaste, envíame el comprobante por aquí.\n— {prestamista}",
        'receipt' => "✅ Pago recibido, {nombre}.\n\nAbono: *{monto}* ({fecha})[[\nSaldo pendiente: *{saldo}*]][[\n\n📄 Tu recibo: {enlace}]]\n— {prestamista}",
        'promise' => "Hola {nombre} 👋\n\nHoy es el día que acordamos para tu pago de *{monto}*.\nQuedo atento. ¡Gracias por cumplir! 🙏\n— {prestamista}",
    ];

    public const LABELS = [
        'reminder' => 'Recordatorio de cuota (víspera y día de pago)',
        'overdue' => 'Cuota vencida',
        'receipt' => 'Recibo de pago',
        'promise' => 'Día de la promesa de pago',
    ];

    public static function render(Company $company, string $key, array $vars): string
    {
        $template = $company->setting("templates.{$key}") ?: self::DEFAULTS[$key];

        $hidden = array_keys(array_filter([
            'saldo' => ! $company->setting('notify.include_balance'),
            'enlace' => ! $company->setting('notify.include_link'),
        ]));

        // Bloques opcionales [[ ... ]]: fuera si usan una variable oculta; si no, se dejan sin corchetes.
        $template = preg_replace_callback('/\[\[(.*?)\]\]/s', function ($m) use ($hidden) {
            foreach ($hidden as $var) {
                if (str_contains($m[1], '{'.$var.'}')) {
                    return '';
                }
            }

            return $m[1];
        }, $template);

        // Una variable oculta fuera de [[ ]] (plantilla personalizada) se vacía en vez de mostrarse.
        foreach ($hidden as $var) {
            $vars[$var] = '';
        }

        $text = strtr($template, collect($vars + ['prestamista' => $company->displayName()])
            ->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
