<?php

return [
    // Días de prueba gratis al registrarse una empresa.
    'trial_days' => (int) env('SAAS_TRIAL_DAYS', 14),

    // Días de gracia después del vencimiento antes de bloquear el panel.
    'grace_days' => (int) env('SAAS_GRACE_DAYS', 3),

    // Cómo pagar la suscripción (mientras el cobro sea manual). Se muestra en la página "Suscripción".
    'payment_instructions' => env('SAAS_PAYMENT_INSTRUCTIONS', "Transfiere a Nequi 3145561727 o Bancolombia ahorros 000-000000-00 a nombre de AF Developer, y envía el comprobante por WhatsApp."),
    'support_whatsapp' => env('SAAS_SUPPORT_WHATSAPP', '573145561727'),

    // Protección del número compartido: máximo de WhatsApp automáticos por empresa al día (si el plan no define otro).
    'default_daily_messages' => (int) env('SAAS_DAILY_MESSAGES', 150),
];
