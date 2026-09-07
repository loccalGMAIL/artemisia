<?php

namespace App\Filament\Resources\Rubros\Pages;

use App\Filament\Resources\Rubros\RubroResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRubros extends ListRecords
{
    protected static string $resource = RubroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
