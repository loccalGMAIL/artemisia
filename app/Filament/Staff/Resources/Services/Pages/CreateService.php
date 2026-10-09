<?php

namespace App\Filament\Staff\Resources\Services\Pages;

use App\Actions\CreateServiceAction;
use App\Filament\Staff\Resources\Services\ServiceResource;
use App\Filament\Support\FormValidation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateServiceAction::class)->handle($data, Auth::user());
        } catch (ValidationException $exception) {
            throw FormValidation::prefixed($exception, 'data');
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('budgets.services.notifications.created');
    }
}
