<?php

namespace App\Filament\Platform\Resources\SubscriptionPayments\Pages;

use App\Filament\Platform\Resources\SubscriptionPayments\SubscriptionPaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionPayments extends ListRecords
{
    protected static string $resource = SubscriptionPaymentResource::class;
}
