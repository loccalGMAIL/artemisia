<?php

namespace App\Filament\Staff\Resources\Services;

use App\Filament\Staff\Resources\Services\Pages\CreateService;
use App\Filament\Staff\Resources\Services\Pages\EditService;
use App\Filament\Staff\Resources\Services\Pages\ListServices;
use App\Filament\Staff\Resources\Services\RelationManagers\PriceHistoriesRelationManager;
use App\Filament\Staff\Resources\Services\Schemas\ServiceForm;
use App\Filament\Staff\Resources\Services\Tables\ServicesTable;
use App\Models\Service;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Service catalog (spec 002). The rules live in the service Actions and ServicePolicy;
 * this resource only lists, shows and triggers them.
 */
class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $slug = 'services';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getModelLabel(): string
    {
        return __('budgets.services.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('budgets.services.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return ServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PriceHistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
