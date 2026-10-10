<?php

namespace App\Filament\Staff\Resources\Budgets\Pages;

use App\Actions\UpdateBudgetHeaderAction;
use App\Exceptions\BudgetNotEditableException;
use App\Filament\Staff\Resources\Budgets\BudgetResource;
use App\Filament\Support\FormValidation;
use App\Models\Budget;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditBudget extends EditRecord
{
    protected static string $resource = BudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * @param  Budget  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateBudgetHeaderAction::class)->handle($record, $data, Auth::user());
        } catch (ValidationException $exception) {
            throw FormValidation::prefixed($exception, 'data');
        } catch (BudgetNotEditableException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            throw new Halt;
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('budgets.notifications.saved');
    }
}
