<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Active = 'activo';
    case Paid = 'pagado';
    case Late = 'atrasado';
    case Cancelled = 'cancelado';
}
