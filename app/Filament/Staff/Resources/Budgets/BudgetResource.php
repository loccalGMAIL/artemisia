<?php

namespace App\Filament\Staff\Resources\Budgets;

use App\Filament\Staff\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Staff\Resources\Budgets\Pages\EditBudget;
use App\Filament\Staff\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Staff\Resources\Budgets\Pages\ViewBudget;
use App\Filament\Staff\Resources\Budgets\RelationManagers\HistoriesRelationManager;
use App\Filament\Staff\Resources\Budgets\RelationManagers\ItemsRelationManager;
use App\Filament\Staff\Resources\Budgets\Schemas\BudgetForm;
use App\Filament\Staff\Resources\Budgets\Schemas\BudgetInfolist;
use App\Filament\Staff\Resources\Budgets\Tables\BudgetsTable;
use App\Models\Budget;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Budgets (spec 002). The rules live in the budget Actions and BudgetPolicy; this resource
 * only lists, shows and triggers them.
 */
class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static ?string $slug = 'budgets';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function getModelLabel(): string
    {
        return __('budgets.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('budgets.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return BudgetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BudgetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BudgetsTable::configure($table);
    }

    /**
     * Discarded budgets stay reachable by their address (RF-52); the list hides them.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            HistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgets::route('/'),
            'create' => CreateBudget::route('/create'),
            'view' => ViewBudget::route('/{record}'),
            'edit' => EditBudget::route('/{record}/edit'),
        ];
    }
}
