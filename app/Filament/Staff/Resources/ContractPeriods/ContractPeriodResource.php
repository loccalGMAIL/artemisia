<?php

namespace App\Filament\Staff\Resources\ContractPeriods;

use App\Filament\Staff\Resources\ContractPeriods\Pages\ListContractPeriods;
use App\Filament\Staff\Resources\ContractPeriods\Pages\ViewContractPeriod;
use App\Filament\Staff\Resources\ContractPeriods\RelationManagers\ConceptsRelationManager;
use App\Filament\Staff\Resources\ContractPeriods\RelationManagers\HistoriesRelationManager;
use App\Filament\Staff\Resources\ContractPeriods\RelationManagers\PaymentsRelationManager;
use App\Filament\Staff\Resources\ContractPeriods\Schemas\ContractPeriodInfolist;
use App\Filament\Staff\Resources\ContractPeriods\Tables\ContractPeriodsTable;
use App\Models\ContractPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The monthly periods of the contracts (spec 005). The rules live in the period Actions and the
 * policies; this resource only lists and shows the periods and triggers those Actions.
 */
class ContractPeriodResource extends Resource
{
    protected static ?string $model = ContractPeriod::class;

    protected static ?string $slug = 'contract-periods';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getModelLabel(): string
    {
        return __('contracts.periods.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('contracts.periods.plural');
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContractPeriodInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractPeriodsTable::configure($table);
    }

    /** Periods are generated from the contract, never created by hand. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            ConceptsRelationManager::class,
            PaymentsRelationManager::class,
            HistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContractPeriods::route('/'),
            'view' => ViewContractPeriod::route('/{record}'),
        ];
    }
}
