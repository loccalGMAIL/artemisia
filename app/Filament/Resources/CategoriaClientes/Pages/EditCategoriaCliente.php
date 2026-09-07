<?php

namespace App\Filament\Resources\CategoriaClientes\Pages;

use App\Filament\Resources\CategoriaClientes\CategoriaClienteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCategoriaCliente extends EditRecord
{
    protected static string $resource = CategoriaClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
