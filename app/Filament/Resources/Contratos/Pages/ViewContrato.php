<?php

namespace App\Filament\Resources\Contratos\Pages;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Models\Contrato;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewContrato extends ViewRecord
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generarResumenes')
                ->label('Generar resúmenes')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Contrato $record): bool => $record->resumenesMensuales()->doesntExist())
                ->action(function (Contrato $record): void {
                    $record->generarResumenesMensuales();

                    Notification::make()
                        ->title('Resúmenes mensuales generados')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
