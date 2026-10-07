<?php

namespace App\Filament\Staff\Resources\AccountHistories;

use App\Filament\Staff\Resources\AccountHistories\Pages\ListAccountHistories;
use App\Filament\Staff\Resources\AccountHistories\Tables\AccountHistoriesTable;
use App\Models\AccountHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only view of the account history (RF-38). Only an admin reaches it (AccessLogPolicy).
 */
class AccountHistoryResource extends Resource
{
    protected static ?string $model = AccountHistory::class;

    protected static ?string $slug = 'account-histories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function getModelLabel(): string
    {
        return __('logs.account_history.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('logs.account_history.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('logs.navigation_group');
    }

    public static function table(Table $table): Table
    {
        return AccountHistoriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'author']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountHistories::route('/'),
        ];
    }
}
