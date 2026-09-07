<?php

namespace App\Filament\Resources\CategoriaClientes\Pages;

use App\Filament\Resources\CategoriaClientes\CategoriaClienteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCategoriaCliente extends ViewRecord
{
    protected static string $resource = CategoriaClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
