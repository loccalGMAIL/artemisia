<?php

namespace App\Filament\Staff\Resources\ContractPeriods\Tables;

use App\Enums\PaymentStatus;
use App\Filament\Staff\Resources\ContractPeriods\ContractPeriodResource;
use App\Models\Client;
use App\Models\ContractPeriod;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // The client is shown even when it was archived afterwards (RF-35).
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('client'))
            ->columns([
                TextColumn::make('client.display_name')
                    ->label(__('contracts.fields.client')),
                TextColumn::make('contract_id')
                    ->label(__('contracts.periods.contract'))
                    ->formatStateUsing(fn (int $state): string => "#{$state}"),
                TextColumn::make('month')
                    ->label(__('contracts.periods.month'))
                    ->date('m/Y')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label(__('contracts.periods.due_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('total')
                    ->label(__('contracts.periods.total'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextColumn::make('paid_amount')
                    ->label(__('contracts.periods.paid'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextColumn::make('balance')
                    ->label(__('contracts.periods.balance'))
                    ->state(fn (ContractPeriod $record): string => Money::display($record->balance)),
                TextColumn::make('payment_status')
                    ->label(__('contracts.periods.payment_status'))
                    ->badge()
                    ->state(fn (ContractPeriod $record): PaymentStatus => $record->payment_status)
                    ->color(fn (PaymentStatus $state): string => self::statusColor($state))
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label()),
                TextColumn::make('overdue')
                    ->label(__('contracts.periods.overdue_column'))
                    ->badge()
                    ->color('danger')
                    ->state(fn (ContractPeriod $record): ?string => $record->isOverdue() ? __('contracts.periods.overdue') : null),
            ])
            ->defaultSort('month', 'desc')
            ->filters([
                SelectFilter::make('client_id')
                    ->label(__('contracts.fields.client'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Client::query()
                        ->search($search)
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Client $client): array => [$client->id => $client->display_name])
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Client::withTrashed()->find($value)?->display_name),
                SelectFilter::make('payment_status')
                    ->label(__('contracts.periods.payment_status'))
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $status): array => [$status->value => $status->label()])->all())
                    ->query(fn (Builder $query, array $data): Builder => self::filterByPaymentStatus($query, $data)),
                Filter::make('overdue')
                    ->label(__('contracts.periods.only_overdue'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->overdue()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->recordUrl(fn (ContractPeriod $record): string => ContractPeriodResource::getUrl('view', ['record' => $record]));
    }

    /**
     * @param  Builder<ContractPeriod>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<ContractPeriod>
     */
    private static function filterByPaymentStatus(Builder $query, array $data): Builder
    {
        return match (PaymentStatus::tryFrom((string) ($data['value'] ?? ''))) {
            PaymentStatus::Pending => $query->pending(),
            PaymentStatus::Partial => $query->partial(),
            PaymentStatus::Paid => $query->paid(),
            null => $query,
        };
    }

    public static function statusColor(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Pending => 'warning',
            PaymentStatus::Partial => 'info',
            PaymentStatus::Paid => 'success',
        };
    }
}
