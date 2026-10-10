<?php

namespace App\Filament\Staff\Resources\ContractPeriods\RelationManagers;

use App\Enums\PaymentMethod;
use App\Filament\Staff\Resources\ContractPeriods\PeriodActions;
use App\Models\Payment;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The payments of the period, the valid ones and the voided ones with their justification
 * (RF-77, RF-85), and the voiding of a valid one (RF-75).
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contracts.relations.payments');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paid_on')->label(__('contracts.payments.paid_on'))->date('d/m/Y'),
                TextColumn::make('amount')
                    ->label(__('contracts.payments.amount'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextColumn::make('method')
                    ->label(__('contracts.payments.method'))
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                TextColumn::make('reference')->label(__('contracts.payments.reference'))->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('contracts.payments.status'))
                    ->badge()
                    ->state(fn (Payment $record): string => $record->isVoided() ? __('contracts.payments.voided') : __('contracts.payments.valid'))
                    ->color(fn (Payment $record): string => $record->isVoided() ? 'danger' : 'success'),
                TextColumn::make('void_justification')->label(__('contracts.payments.void_justification'))->placeholder('—'),
            ])
            ->recordActions([
                PeriodActions::voidPayment(),
            ])
            ->paginated(false);
    }
}
