<?php

namespace App\Filament\Resources\Servicios;

use App\Filament\Resources\Servicios\Pages\CreateServicio;
use App\Filament\Resources\Servicios\Pages\EditServicio;
use App\Filament\Resources\Servicios\Pages\ListServicios;
use App\Filament\Resources\Servicios\Pages\ViewServicio;
use App\Filament\Resources\Servicios\RelationManagers\PreciosRelationManager;
use App\Filament\Resources\Servicios\Schemas\ServicioForm;
use App\Filament\Resources\Servicios\Schemas\ServicioInfolist;
use App\Filament\Resources\Servicios\Tables\ServiciosTable;
use App\Models\Servicio;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ServicioResource extends Resource
{
    protected static ?string $model = Servicio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $modelLabel = 'servicio';

    protected static ?string $pluralModelLabel = 'Servicios';

    public static function form(Schema $schema): Schema
    {
        return ServicioForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ServicioInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiciosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PreciosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServicios::route('/'),
            'create' => CreateServicio::route('/create'),
            'view' => ViewServicio::route('/{record}'),
            'edit' => EditServicio::route('/{record}/edit'),
        ];
    }
}
