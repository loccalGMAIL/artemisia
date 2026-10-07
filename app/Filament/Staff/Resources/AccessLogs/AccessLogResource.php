<?php

namespace App\Filament\Staff\Resources\AccessLogs;

use App\Filament\Staff\Resources\AccessLogs\Pages\ListAccessLogs;
use App\Filament\Staff\Resources\AccessLogs\Tables\AccessLogsTable;
use App\Models\AccessLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only view of the access log (RF-38). Only an admin reaches it (AccessLogPolicy).
 */
class AccessLogResource extends Resource
{
    protected static ?string $model = AccessLog::class;

    protected static ?string $slug = 'access-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    public static function getModelLabel(): string
    {
        return __('logs.access_log.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('logs.access_log.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('logs.navigation_group');
    }

    public static function table(Table $table): Table
    {
        return AccessLogsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessLogs::route('/'),
        ];
    }
}
