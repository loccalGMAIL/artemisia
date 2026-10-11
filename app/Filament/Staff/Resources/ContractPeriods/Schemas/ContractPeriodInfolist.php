<?php

namespace App\Filament\Staff\Resources\ContractPeriods\Schemas;

use App\Enums\PaymentStatus;
use App\Filament\Staff\Resources\ContractPeriods\Tables\ContractPeriodsTable;
use App\Models\ContractPeriod;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The record card of a period. Its extras and discounts, its payments and its history are the
 * relation managers below.
 */
class ContractPeriodInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('contracts.periods.sections.data'))->schema([
                TextEntry::make('client.display_name')->label(__('contracts.fields.client')),
                TextEntry::make('contract_id')
                    ->label(__('contracts.periods.contract'))
                    ->formatStateUsing(fn (int $state): string => "#{$state}"),
                TextEntry::make('month')->label(__('contracts.periods.month'))->date('m/Y'),
                TextEntry::make('due_date')->label(__('contracts.periods.due_date'))->date('d/m/Y'),
                TextEntry::make('payment_status')
                    ->label(__('contracts.periods.payment_status'))
                    ->badge()
                    ->state(fn (ContractPeriod $record): PaymentStatus => $record->payment_status)
                    ->color(fn (PaymentStatus $state): string => ContractPeriodsTable::statusColor($state))
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label()),
                TextEntry::make('overdue')
                    ->label(__('contracts.periods.overdue_column'))
                    ->badge()
                    ->color('danger')
                    ->state(fn (): string => __('contracts.periods.overdue'))
                    ->visible(fn (ContractPeriod $record): bool => $record->isOverdue()),
            ])->columns(2),
            Section::make(__('contracts.periods.sections.amounts'))->schema([
                TextEntry::make('fee')
                    ->label(__('contracts.periods.fee'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextEntry::make('total')
                    ->label(__('contracts.periods.total'))
                    ->weight('bold')
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextEntry::make('paid_amount')
                    ->label(__('contracts.periods.paid'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextEntry::make('balance')
                    ->label(__('contracts.periods.balance'))
                    ->state(fn (ContractPeriod $record): string => Money::display($record->balance)),
            ])->columns(4),
            Section::make(__('contracts.periods.sections.invoice'))->schema([
                TextEntry::make('invoice_number')
                    ->label(__('contracts.periods.invoice_number'))
                    ->placeholder(__('contracts.periods.no_invoice')),
                TextEntry::make('invoice_date')
                    ->label(__('contracts.periods.invoice_date'))
                    ->date('d/m/Y')
                    ->placeholder(__('contracts.periods.no_invoice')),
            ])->columns(2),
        ]);
    }
}
