<?php

namespace App\Filament\Staff\Resources\Contracts\Schemas;

use App\Enums\ContractStatus;
use App\Filament\Staff\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The record card of a contract. Its periods and its history are the relation managers below.
 */
class ContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('contracts.sections.data'))->schema([
                TextEntry::make('client.display_name')->label(__('contracts.fields.client')),
                TextEntry::make('status')
                    ->label(__('contracts.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (ContractStatus $state): string => $state->label()),
                TextEntry::make('monthly_amount')
                    ->label(__('contracts.fields.monthly_amount'))
                    ->weight('bold')
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextEntry::make('due_day')->label(__('contracts.fields.due_day')),
                TextEntry::make('start_date')->label(__('contracts.fields.start_date'))->date('d/m/Y'),
                TextEntry::make('term')
                    ->label(__('contracts.fields.term'))
                    ->state(fn (Contract $record): string => ContractsTable::termLabel($record)),
                TextEntry::make('term_end_month')
                    ->label(__('contracts.fields.term_end_month'))
                    ->date('m/Y')
                    ->visible(fn (Contract $record): bool => ! $record->isIndefinite()),
                TextEntry::make('budget_id')
                    ->label(__('contracts.fields.budget'))
                    ->formatStateUsing(fn (int $state): string => "#{$state}"),
                TextEntry::make('creator.name')->label(__('contracts.fields.creator')),
            ])->columns(2),
        ]);
    }
}
