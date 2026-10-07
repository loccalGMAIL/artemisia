<?php

namespace App\Filament\Staff\Resources\Accounts\Pages;

use App\Actions\CreateAccountAction;
use App\Exceptions\DuplicateAccountEmailException;
use App\Filament\Staff\Resources\Accounts\AccountResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateAccountAction::class)->handle($data, Auth::user());
        } catch (DuplicateAccountEmailException $exception) {
            throw ValidationException::withMessages(['data.email' => $exception->getMessage()]);
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('accounts.notifications.created');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
