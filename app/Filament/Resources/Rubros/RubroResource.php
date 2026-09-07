<?php

namespace App\Filament\Resources\Rubros;

use App\Filament\Resources\Rubros\Pages\CreateRubro;
use App\Filament\Resources\Rubros\Pages\EditRubro;
use App\Filament\Resources\Rubros\Pages\ListRubros;
use App\Filament\Resources\Rubros\Pages\ViewRubro;
use App\Filament\Resources\Rubros\Schemas\RubroForm;
use App\Filament\Resources\Rubros\Schemas\RubroInfolist;
use App\Filament\Resources\Rubros\Tables\RubrosTable;
use App\Models\Rubro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RubroResource extends Resource
{
    protected static ?string $model = Rubro::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $modelLabel = 'rubro';

    protected static ?string $pluralModelLabel = 'Rubros';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return RubroForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RubroInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RubrosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRubros::route('/'),
            'create' => CreateRubro::route('/create'),
            'view' => ViewRubro::route('/{record}'),
            'edit' => EditRubro::route('/{record}/edit'),
        ];
    }
}
