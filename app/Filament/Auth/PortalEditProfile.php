<?php

namespace App\Filament\Auth;

use App\Actions\ChangeOwnPasswordAction;
use App\Actions\UpdateAccountProfileAction;
use App\Filament\Support\FormValidation;
use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;
use SensitiveParameter;

/**
 * Own profile of an account in either portal (spec 006): name and password can be changed, the
 * email is shown but only an admin changes it. It only collects the data and shows the errors;
 * every rule lives in UpdateAccountProfileAction and ChangeOwnPasswordAction.
 */
class PortalEditProfile extends EditProfile
{
    /** @var array{current: string, new: string}|null */
    private ?array $passwordChange = null;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            Text::make(__('accounts.profile.no_password'))->visible(fn (): bool => ! $this->hasPassword()),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label(__('accounts.fields.name'))
            ->required()
            ->maxLength(150)
            ->autofocus();
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('accounts.fields.email'))
            ->helperText(__('accounts.profile.email_hint'))
            ->disabled()
            ->dehydrated(false);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('accounts.profile.new_password'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('new-password')
            ->visible(fn (): bool => $this->hasPassword())
            ->dehydrated(fn (#[SensitiveParameter] $state): bool => filled($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('accounts.profile.new_password_confirmation'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('new-password')
            ->required()
            ->visible(fn (Get $get): bool => $this->hasPassword() && filled($get('password')))
            ->dehydrated(false);
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('currentPassword')
            ->label(__('accounts.profile.current_password'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required(fn (Get $get): bool => filled($get('password')))
            ->visible(fn (): bool => $this->hasPassword());
    }

    /**
     * The password never travels with the rest of the data: the parent page would store it as the
     * session hash, which is the job of ChangeOwnPasswordAction.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        $this->passwordChange = filled($data['password'] ?? null)
            ? ['current' => (string) ($data['currentPassword'] ?? ''), 'new' => (string) $data['password']]
            : null;

        return ['name' => $data['name']];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $account = $record instanceof User ? $record : throw new LogicException('The profile belongs to an account.');

        try {
            app(UpdateAccountProfileAction::class)->handle($account, ['name' => (string) $data['name']], $account);

            if ($this->passwordChange !== null) {
                app(ChangeOwnPasswordAction::class)->handle($account, $this->passwordChange['current'], $this->passwordChange['new']);
            }
        } catch (ValidationException $exception) {
            throw FormValidation::prefixed($exception, 'data');
        }

        return $account;
    }

    protected function afterSave(): void
    {
        $this->data['currentPassword'] = null;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('accounts.notifications.profile_saved');
    }

    private function hasPassword(): bool
    {
        return filled($this->getUser()->getAuthPassword());
    }
}
