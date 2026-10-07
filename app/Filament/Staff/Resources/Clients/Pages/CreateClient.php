<?php

namespace App\Filament\Staff\Resources\Clients\Pages;

use App\Actions\CreateClientAction;
use App\Filament\Staff\Resources\Clients\ClientResource;
use App\Filament\Support\FormValidation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateClientAction::class)->handle($data, Auth::user());
        } catch (ValidationException $exception) {
            throw FormValidation::prefixed($exception, 'data');
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('clients.notifications.created');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
