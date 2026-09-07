<?php

namespace App\Filament\Resources\CategoriaClientes;

use App\Filament\Resources\CategoriaClientes\Pages\CreateCategoriaCliente;
use App\Filament\Resources\CategoriaClientes\Pages\EditCategoriaCliente;
use App\Filament\Resources\CategoriaClientes\Pages\ListCategoriaClientes;
use App\Filament\Resources\CategoriaClientes\Pages\ViewCategoriaCliente;
use App\Filament\Resources\CategoriaClientes\Schemas\CategoriaClienteForm;
use App\Filament\Resources\CategoriaClientes\Schemas\CategoriaClienteInfolist;
use App\Filament\Resources\CategoriaClientes\Tables\CategoriaClientesTable;
use App\Models\CategoriaCliente;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CategoriaClienteResource extends Resource
{
    protected static ?string $model = CategoriaCliente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $modelLabel = 'categoría de cliente';

    protected static ?string $pluralModelLabel = 'Categorías de cliente';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CategoriaClienteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CategoriaClienteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriaClientesTable::configure($table);
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
            'index' => ListCategoriaClientes::route('/'),
            'create' => CreateCategoriaCliente::route('/create'),
            'view' => ViewCategoriaCliente::route('/{record}'),
            'edit' => EditCategoriaCliente::route('/{record}/edit'),
        ];
    }
}
