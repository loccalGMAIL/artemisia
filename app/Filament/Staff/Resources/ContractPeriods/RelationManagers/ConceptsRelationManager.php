<?php

namespace App\Filament\Staff\Resources\ContractPeriods\RelationManagers;

use App\Enums\ConceptType;
use App\Filament\Staff\Resources\ContractPeriods\PeriodActions;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The extras and discounts of the period, with the description or the justification (RF-85),
 * and the removal of each one (RF-47).
 */
class ConceptsRelationManager extends RelationManager
{
    protected static string $relationship = 'concepts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contracts.relations.concepts');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('contracts.concepts.type'))
                    ->badge()
                    ->color(fn (ConceptType $state): string => $state === ConceptType::Extra ? 'info' : 'warning')
                    ->formatStateUsing(fn (ConceptType $state): string => $state->label()),
                TextColumn::make('description')->label(__('contracts.concepts.description')),
                TextColumn::make('amount')
                    ->label(__('contracts.concepts.amount'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
            ])
            ->recordActions([
                PeriodActions::removeConcept(),
            ])
            ->paginated(false);
    }
}
