<?php

namespace App\Filament\Staff\Resources\Clients\RelationManagers;

use App\Enums\ContractStatus;
use App\Filament\Staff\Resources\Contracts\ContractResource;
use App\Filament\Staff\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The contracts of the client, a tab of its card (spec 005, RF-91): number, monthly amount,
 * start, term and status, newest first, each one linking to its own card.
 */
class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contracts.plural');
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
            ->columns([
                TextColumn::make('id')
                    ->label(__('contracts.fields.id'))
                    ->formatStateUsing(fn (int $state): string => "#{$state}")
                    ->url(fn (Contract $record): string => ContractResource::getUrl('view', ['record' => $record])),
                TextColumn::make('monthly_amount')
                    ->label(__('contracts.fields.monthly_amount'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextColumn::make('start_date')->label(__('contracts.fields.start_date'))->date('d/m/Y'),
                TextColumn::make('term')
                    ->label(__('contracts.fields.term'))
                    ->state(fn (Contract $record): string => ContractsTable::termLabel($record)),
                TextColumn::make('status')
                    ->label(__('contracts.fields.status'))
                    ->badge()
                    ->color(fn (ContractStatus $state): string => match ($state) {
                        ContractStatus::Active => 'success',
                        ContractStatus::Finished => 'gray',
                        ContractStatus::Cancelled => 'danger',
                    })
                    ->formatStateUsing(fn (ContractStatus $state): string => $state->label()),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading(__('contracts.client_section.contracts_empty'))
            ->paginated([10, 25, 50]);
    }
}
