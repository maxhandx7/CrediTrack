<?php

return [
    // false = solo el administrador crea prestamistas (recomendado).
    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', false),

    // Días de atraso en los que se le recuerda al cliente (no todos los días).
    'overdue_reminder_days' => [1, 3, 7, 15, 30],

    'login_code_minutes' => 10,
];
