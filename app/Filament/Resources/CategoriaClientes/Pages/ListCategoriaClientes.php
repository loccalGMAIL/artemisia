<?php

namespace App\Filament\Resources\CategoriaClientes\Pages;

use App\Filament\Resources\CategoriaClientes\CategoriaClienteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoriaClientes extends ListRecords
{
    protected static string $resource = CategoriaClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
