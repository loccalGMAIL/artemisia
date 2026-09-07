<?php

namespace App\Filament\Resources\Rubros\Pages;

use App\Filament\Resources\Rubros\RubroResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRubro extends EditRecord
{
    protected static string $resource = RubroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
