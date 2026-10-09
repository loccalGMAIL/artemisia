<?php

namespace App\Filament\Staff\Resources\Budgets\Pages;

use App\Filament\Staff\Resources\Budgets\BudgetResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBudget extends ViewRecord
{
    protected static string $resource = BudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => $this->getRecord()->status->isEditable() && ! $this->getRecord()->trashed()),
        ];
    }
}
