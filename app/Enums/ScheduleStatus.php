<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case Pending = 'pendiente';
    case Paid = 'pagado';
    case Overdue = 'vencido';
}
