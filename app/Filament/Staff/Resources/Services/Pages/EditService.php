<?php

namespace App\Filament\Staff\Resources\Services\Pages;

use App\Actions\UpdateServiceAction;
use App\Filament\Staff\Resources\Services\ServiceActions;
use App\Filament\Staff\Resources\Services\ServiceResource;
use App\Filament\Support\FormValidation;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ServiceActions::toggleActive(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateServiceAction::class)->handle($record, $data, Auth::user());
        } catch (ValidationException $exception) {
            throw FormValidation::prefixed($exception, 'data');
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('budgets.services.notifications.saved');
    }
}
