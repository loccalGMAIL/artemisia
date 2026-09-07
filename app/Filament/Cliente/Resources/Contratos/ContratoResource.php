<?php

namespace App\Filament\Cliente\Resources\Contratos;

use App\Filament\Cliente\Resources\Contratos\Pages\ListContratos;
use App\Filament\Cliente\Resources\Contratos\Pages\ViewContrato;
use App\Filament\Cliente\Resources\Contratos\Schemas\ContratoInfolist;
use App\Filament\Cliente\Resources\Contratos\Tables\ContratosTable;
use App\Models\Contrato;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContratoResource extends Resource
{
    protected static ?string $model = Contrato::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'contrato';

    protected static ?string $pluralModelLabel = 'Contratos';

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
        return ContratoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContratosTable::configure($table);
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
            'index' => ListContratos::route('/'),
            'view' => ViewContrato::route('/{record}'),
        ];
    }
}
