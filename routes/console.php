<?php

use Illuminate\Support\Facades\Schedule;

// Cobranza: cada hora; cada empresa envía a la hora que eligió (por defecto 8 a. m.).
Schedule::command('creditrack:collections')->hourlyAt(0)->withoutOverlapping();

// Suscripciones: marca vencidas las pruebas y los pagos que expiraron.
Schedule::command('saas:subscriptions')->dailyAt('00:10');

Schedule::command('queue:prune-failed --hours=720')->weekly();
