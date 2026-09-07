<?php

namespace App\Filament\Resources\Produccions;

use App\Filament\Resources\Produccions\Pages\EditProduccion;
use App\Filament\Resources\Produccions\Pages\ListProduccions;
use App\Filament\Resources\Produccions\Pages\ViewProduccion;
use App\Filament\Resources\Produccions\Schemas\ProduccionForm;
use App\Filament\Resources\Produccions\Schemas\ProduccionInfolist;
use App\Filament\Resources\Produccions\Tables\ProduccionsTable;
use App\Models\Produccion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProduccionResource extends Resource
{
    protected static ?string $model = Produccion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Gestión';

    protected static ?string $modelLabel = 'producción';

    protected static ?string $pluralModelLabel = 'Producciones';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ProduccionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduccionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduccionsTable::configure($table);
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
            'index' => ListProduccions::route('/'),
            'view' => ViewProduccion::route('/{record}'),
            'edit' => EditProduccion::route('/{record}/edit'),
        ];
    }
}
