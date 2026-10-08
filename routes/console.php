<?php

use Illuminate\Support\Facades\Schedule;

// Cobranza: estados al día + recordatorios por WhatsApp. A las 8 a. m., hora de Colombia.
Schedule::command('creditrack:collections')->dailyAt('08:00')->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=720')->weekly();
