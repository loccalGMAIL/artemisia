<?php

namespace App\Filament\Staff\Resources\Clients\RelationManagers;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The payment history of the client, a tab of its card (spec 005, RF-92): every payment received
 * through its contracts, the valid ones and the voided ones with their justification, newest
 * first.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contracts.client_section.payments_title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('contractPeriod'))
            ->columns([
                TextColumn::make('paid_on')->label(__('contracts.payments.paid_on'))->date('d/m/Y')->sortable(),
                TextColumn::make('amount')
                    ->label(__('contracts.payments.amount'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextColumn::make('method')
                    ->label(__('contracts.payments.method'))
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                TextColumn::make('reference')->label(__('contracts.payments.reference'))->placeholder('—'),
                TextColumn::make('period')
                    ->label(__('contracts.periods.month'))
                    ->state(fn (Payment $record): string => $record->contractPeriod->month->format('m/Y')),
                TextColumn::make('status')
                    ->label(__('contracts.payments.status'))
                    ->badge()
                    ->state(fn (Payment $record): string => $record->isVoided() ? __('contracts.payments.voided') : __('contracts.payments.valid'))
                    ->color(fn (Payment $record): string => $record->isVoided() ? 'danger' : 'success'),
                TextColumn::make('void_justification')->label(__('contracts.payments.void_justification'))->placeholder('—'),
            ])
            ->defaultSort('payments.paid_on', 'desc')
            ->emptyStateHeading(__('contracts.client_section.payments_empty'))
            ->paginated([10, 25, 50]);
    }
}
