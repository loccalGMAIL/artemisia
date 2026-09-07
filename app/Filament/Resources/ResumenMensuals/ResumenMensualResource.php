<?php

namespace App\Filament\Resources\ResumenMensuals;

use App\Filament\Resources\ResumenMensuals\Pages\EditResumenMensual;
use App\Filament\Resources\ResumenMensuals\Pages\ListResumenMensuals;
use App\Filament\Resources\ResumenMensuals\Pages\ViewResumenMensual;
use App\Filament\Resources\ResumenMensuals\Schemas\ResumenMensualForm;
use App\Filament\Resources\ResumenMensuals\Schemas\ResumenMensualInfolist;
use App\Filament\Resources\ResumenMensuals\Tables\ResumenMensualsTable;
use App\Models\ResumenMensual;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ResumenMensualResource extends Resource
{
    protected static ?string $model = ResumenMensual::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Gestión';

    protected static ?string $modelLabel = 'resumen mensual';

    protected static ?string $pluralModelLabel = 'Resúmenes mensuales';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ResumenMensualForm::configure($schema);
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
            'edit' => EditResumenMensual::route('/{record}/edit'),
        ];
    }
}
