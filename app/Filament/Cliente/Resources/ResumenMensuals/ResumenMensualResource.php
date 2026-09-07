<?php

namespace App\Filament\Cliente\Resources\ResumenMensuals;

use App\Filament\Cliente\Resources\ResumenMensuals\Pages\ListResumenMensuals;
use App\Filament\Cliente\Resources\ResumenMensuals\Pages\ViewResumenMensual;
use App\Filament\Cliente\Resources\ResumenMensuals\Schemas\ResumenMensualInfolist;
use App\Filament\Cliente\Resources\ResumenMensuals\Tables\ResumenMensualsTable;
use App\Models\ResumenMensual;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ResumenMensualResource extends Resource
{
    protected static ?string $model = ResumenMensual::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'resumen mensual';

    protected static ?string $pluralModelLabel = 'Resúmenes mensuales';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('contrato.presupuesto', fn (Builder $query) => $query->where('cliente_id', auth()->user()->cliente_id));
    }

    public static function infolist(Schema $schema): Schema
    {
        return ResumenMensualInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResumenMensualsTable::configure($table);
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
            'index' => ListResumenMensuals::route('/'),
            'view' => ViewResumenMensual::route('/{record}'),
        ];
    }
}
