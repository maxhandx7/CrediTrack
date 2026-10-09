<?php

namespace App\Services;

use App\Models\User;

/**
 * Textos de WhatsApp editables por cada prestamista (Configuración → Mensajes).
 * Variables: {nombre} {monto} {fecha} {saldo} {dias} {prestamista} {enlace}
 */
class MessageTemplates
{
    public const DEFAULTS = [
        'reminder' => "Hola {nombre} 👋\n\nTe recuerdo que {fecha} vence tu cuota de *{monto}*.\nSaldo total del préstamo: {saldo}.\n\nSi ya pagaste, ignora este mensaje. ¡Gracias! 🙏\n— {prestamista}",
        'overdue' => "Hola {nombre}.\n\nTu cuota de *{monto}* que vencía {fecha} tiene {dias} día(s) de atraso.\n¿Me confirmas cuándo te pones al día? Si ya pagaste, envíame el comprobante por aquí.\n— {prestamista}",
        'receipt' => "✅ Pago recibido, {nombre}.\n\nAbono: *{monto}* ({fecha})\nSaldo pendiente: *{saldo}*\n\n📄 Tu recibo: {enlace}\n— {prestamista}",
        'promise' => "Hola {nombre} 👋\n\nHoy es el día que acordamos para tu pago de *{monto}*.\nQuedo atento. ¡Gracias por cumplir! 🙏\n— {prestamista}",
    ];

    public const LABELS = [
        'reminder' => 'Recordatorio de cuota (víspera y día de pago)',
        'overdue' => 'Cuota vencida',
        'receipt' => 'Recibo de pago',
        'promise' => 'Día de la promesa de pago',
    ];

    public static function render(User $lender, string $key, array $vars): string
    {
        $template = $lender->setting("templates.{$key}") ?: self::DEFAULTS[$key];

        return strtr($template, collect($vars + ['prestamista' => $lender->displayName()])
            ->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }
}
