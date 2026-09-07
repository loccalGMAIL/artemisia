<?php

namespace App\Filament\Cliente\Resources\Produccions;

use App\Filament\Cliente\Resources\Produccions\Pages\ListProduccions;
use App\Filament\Cliente\Resources\Produccions\Pages\ViewProduccion;
use App\Filament\Cliente\Resources\Produccions\Schemas\ProduccionInfolist;
use App\Filament\Cliente\Resources\Produccions\Tables\ProduccionsTable;
use App\Models\Produccion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProduccionResource extends Resource
{
    protected static ?string $model = Produccion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'producción';

    protected static ?string $pluralModelLabel = 'Producciones';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('presupuesto', fn (Builder $query) => $query->where('cliente_id', auth()->user()->cliente_id));
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
        ];
    }
}
