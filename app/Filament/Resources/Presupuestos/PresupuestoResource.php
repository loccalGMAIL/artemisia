<?php

namespace App\Filament\Resources\Presupuestos;

use App\Enums\EstadoPresupuesto;
use App\Filament\Resources\Presupuestos\Pages\CreatePresupuesto;
use App\Filament\Resources\Presupuestos\Pages\EditPresupuesto;
use App\Filament\Resources\Presupuestos\Pages\ListPresupuestos;
use App\Filament\Resources\Presupuestos\Pages\ViewPresupuesto;
use App\Filament\Resources\Presupuestos\RelationManagers\CostosProveedorRelationManager;
use App\Filament\Resources\Presupuestos\Schemas\PresupuestoForm;
use App\Filament\Resources\Presupuestos\Schemas\PresupuestoInfolist;
use App\Filament\Resources\Presupuestos\Tables\PresupuestosTable;
use App\Models\Contrato;
use App\Models\Presupuesto;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PresupuestoResource extends Resource
{
    protected static ?string $model = Presupuesto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Gestión';

    protected static ?string $modelLabel = 'presupuesto';

    protected static ?string $pluralModelLabel = 'Presupuestos';

    public static function form(Schema $schema): Schema
    {
        return PresupuestoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PresupuestoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PresupuestosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CostosProveedorRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPresupuestos::route('/'),
            'create' => CreatePresupuesto::route('/create'),
            'view' => ViewPresupuesto::route('/{record}'),
            'edit' => EditPresupuesto::route('/{record}/edit'),
        ];
    }

    /**
     * Acción reutilizable (tabla y páginas) que confirma el presupuesto y genera
     * el Contrato o la Producción correspondiente.
     */
    public static function confirmarAction(): Action
    {
        return Action::make('confirmar')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Se generará un contrato o una producción según los rubros del presupuesto.')
            ->visible(fn (Presupuesto $record): bool => $record->estado !== EstadoPresupuesto::Confirmado)
            ->action(function (Presupuesto $record): void {
                $resultado = $record->confirmar();

                Notification::make()
                    ->title($resultado instanceof Contrato ? 'Contrato generado' : 'Producción generada')
                    ->success()
                    ->send();
            });
    }
}
