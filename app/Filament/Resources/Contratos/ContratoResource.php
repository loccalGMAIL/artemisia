<?php

namespace App\Filament\Resources\Contratos;

use App\Filament\Resources\Contratos\Pages\EditContrato;
use App\Filament\Resources\Contratos\Pages\ListContratos;
use App\Filament\Resources\Contratos\Pages\ViewContrato;
use App\Filament\Resources\Contratos\RelationManagers\ResumenesMensualesRelationManager;
use App\Filament\Resources\Contratos\Schemas\ContratoForm;
use App\Filament\Resources\Contratos\Schemas\ContratoInfolist;
use App\Filament\Resources\Contratos\Tables\ContratosTable;
use App\Models\Contrato;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ContratoResource extends Resource
{
    protected static ?string $model = Contrato::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Gestión';

    protected static ?string $modelLabel = 'contrato';

    protected static ?string $pluralModelLabel = 'Contratos';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ContratoForm::configure($schema);
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
            ResumenesMensualesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContratos::route('/'),
            'view' => ViewContrato::route('/{record}'),
            'edit' => EditContrato::route('/{record}/edit'),
        ];
    }
}
