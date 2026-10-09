<?php

namespace App\Filament\Staff\Resources\Budgets\Pages;

use App\Actions\CreateBudgetAction;
use App\Filament\Staff\Resources\Budgets\BudgetResource;
use App\Filament\Support\FormValidation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateBudget extends CreateRecord
{
    protected static string $resource = BudgetResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateBudgetAction::class)->handle($data, Auth::user());
        } catch (ValidationException $exception) {
            throw FormValidation::prefixed($exception, 'data');
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('budgets.notifications.created');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
