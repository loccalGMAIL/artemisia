<?php

namespace App\Filament\Staff\Resources\Clients\Pages;

use App\Actions\UpdateClientAddressAction;
use App\Actions\UpdateClientIdentificationAction;
use App\Filament\Staff\Resources\Clients\ClientActions;
use App\Filament\Staff\Resources\Clients\ClientResource;
use App\Filament\Support\FormValidation;
use App\Models\Client;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            ...ClientActions::state(),
        ];
    }

    /**
     * @param  Client  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            app(UpdateClientIdentificationAction::class)->handle(
                $record,
                collect($data)->only(['first_name', 'last_name', 'company_name', 'document'])->all(),
                Auth::user(),
            );

            app(UpdateClientAddressAction::class)->handle(
                $record,
                collect($data)->only(['street', 'street_number', 'city', 'province_id', 'postal_code'])->all(),
                Auth::user(),
            );
        } catch (ValidationException $exception) {
            throw FormValidation::prefixed($exception, 'data');
        }

        return $record;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('clients.notifications.saved');
    }
}
