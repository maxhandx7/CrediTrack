<?php

namespace App\Filament\Platform\Resources\Companies\RelationManagers;

use App\Filament\Platform\Resources\SubscriptionPayments\SubscriptionPaymentResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptionPayments';

    protected static ?string $title = 'Pagos de suscripción';

    public function table(Table $table): Table
    {
        return SubscriptionPaymentResource::table($table);
    }
}
