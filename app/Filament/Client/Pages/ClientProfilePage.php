<?php

namespace App\Filament\Client\Pages;

use App\Actions\AddClientContactAction;
use App\Actions\RemoveClientContactAction;
use App\Actions\SetPrimaryContactAction;
use App\Actions\UpdateClientAddressAction;
use App\Actions\UpdateClientContactAction;
use App\Filament\Support\FormValidation;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Province;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Home of the client portal: the record card of the client the signed-in account is linked
 * to (RF-57), where the account can update the address and the contacts (RF-59) but nothing
 * else (RF-60). The client is never taken from the request, only from the account (RF-62).
 */
class ClientProfilePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?int $navigationSort = -2;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public static function getNavigationLabel(): string
    {
        return __('clients.portal.profile');
    }

    public function getTitle(): string|Htmlable
    {
        return __('clients.portal.profile');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->editAddressAction(),
            $this->addContactAction(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $client = $this->linkedClient();
        $decision = Gate::inspect('portal.view', $client);

        if ($decision->denied()) {
            return $schema->components([
                Section::make()->schema([
                    TextEntry::make('notice')->hiddenLabel()->state($decision->message()),
                ]),
            ]);
        }

        return $schema->components([
            Section::make(__('clients.sections.identification'))->schema([
                TextEntry::make('display_name')->label(__('clients.fields.display_name'))->state($client->display_name),
                TextEntry::make('person_type')->label(__('clients.fields.person_type'))->state($client->person_type->label()),
                TextEntry::make('document')->label($client->person_type->documentLabel())->state($client->document),
                TextEntry::make('contact_phone')
                    ->label(__('clients.fields.contact_phone'))
                    ->state($client->contactPhone() ?? __('clients.view.no_contact_phone')),
            ])->columns(2),

            Section::make(__('clients.sections.address'))->schema([
                TextEntry::make('street')->label(__('clients.fields.street'))->state($client->street)->placeholder('—'),
                TextEntry::make('street_number')->label(__('clients.fields.street_number'))->state($client->street_number)->placeholder('—'),
                TextEntry::make('city')->label(__('clients.fields.city'))->state($client->city)->placeholder('—'),
                TextEntry::make('province')->label(__('clients.fields.province'))->state($client->province?->name)->placeholder('—'),
                TextEntry::make('postal_code')->label(__('clients.fields.postal_code'))->state($client->postal_code)->placeholder('—'),
            ])->columns(2),

            Section::make(__('clients.relations.contacts'))->schema(
                $this->contactComponents($client),
            ),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    private function contactComponents(Client $client): array
    {
        $contacts = $client->contacts()->orderByDesc('is_primary')->orderBy('id')->get();

        if ($contacts->isEmpty()) {
            return [TextEntry::make('no_contacts')->hiddenLabel()->state(__('clients.portal.no_contacts'))];
        }

        return $contacts->map(fn (ClientContact $contact): Grid => Grid::make(['default' => 1, 'md' => 6])->schema([
            TextEntry::make("contact_{$contact->id}_name")
                ->label(__('clients.fields.contact_name'))
                ->state($contact->name)
                ->badge($contact->is_primary)
                ->helperText($contact->is_primary ? __('clients.portal.primary_contact') : null),
            TextEntry::make("contact_{$contact->id}_role")->label(__('clients.fields.contact_role'))->state($contact->role)->placeholder('—'),
            TextEntry::make("contact_{$contact->id}_phone")->label(__('clients.fields.phone'))->state($contact->phone)->placeholder('—'),
            TextEntry::make("contact_{$contact->id}_email")->label(__('clients.fields.email'))->state($contact->email)->placeholder('—'),
            Actions::make([
                $this->setPrimaryContactAction()->arguments(['contact' => $contact->id]),
                $this->editContactAction()->arguments(['contact' => $contact->id]),
                $this->removeContactAction()->arguments(['contact' => $contact->id]),
            ])->key("contact_{$contact->id}_actions")->columnSpan(2),
        ]))->all();
    }

    public function editAddressAction(): Action
    {
        return Action::make('editAddress')
            ->label(__('clients.portal.actions.edit_address'))
            ->icon('heroicon-o-map-pin')
            ->visible(fn (): bool => Gate::allows('portal.updateAddress', $this->linkedClient()))
            ->fillForm(fn (): array => $this->linkedClient()?->addressSnapshot() ?? [])
            ->schema([
                TextInput::make('street')->label(__('clients.fields.street'))->maxLength(150),
                TextInput::make('street_number')->label(__('clients.fields.street_number'))->maxLength(20),
                TextInput::make('city')->label(__('clients.fields.city'))->maxLength(100),
                Select::make('province_id')
                    ->label(__('clients.fields.province'))
                    ->options(fn (): array => Province::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                TextInput::make('postal_code')->label(__('clients.fields.postal_code'))->maxLength(15),
            ])
            ->action(function (array $data, Action $action): void {
                $client = $this->authorizedClient('portal.updateAddress');

                $this->perform($action, function () use ($client, $data): void {
                    app(UpdateClientAddressAction::class)->handle($client, $data, Auth::user());
                });

                Notification::make()->success()->title(__('clients.portal.notifications.address_saved'))->send();
            });
    }

    public function addContactAction(): Action
    {
        return Action::make('addContact')
            ->label(__('clients.portal.actions.add_contact'))
            ->icon('heroicon-o-user-plus')
            ->visible(fn (): bool => Gate::allows('portal.updateContacts', $this->linkedClient()))
            ->schema($this->contactFields())
            ->action(function (array $data, Action $action): void {
                $client = $this->authorizedClient('portal.updateContacts');

                $this->perform($action, function () use ($client, $data): void {
                    app(AddClientContactAction::class)->handle($client, $data, Auth::user());
                });

                Notification::make()->success()->title(__('clients.portal.notifications.contact_saved'))->send();
            });
    }

    public function editContactAction(): Action
    {
        return Action::make('editContact')
            ->label(__('clients.portal.actions.edit_contact'))
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->size('sm')
            ->fillForm(function (array $arguments): array {
                $contact = $this->ownContact($arguments);

                return $contact->only(['name', 'role', 'phone', 'email']);
            })
            ->schema($this->contactFields())
            ->action(function (array $data, array $arguments, Action $action): void {
                $contact = $this->ownContact($arguments);

                $this->perform($action, function () use ($contact, $data): void {
                    app(UpdateClientContactAction::class)->handle($contact, $data, Auth::user());
                });

                Notification::make()->success()->title(__('clients.portal.notifications.contact_saved'))->send();
            });
    }

    public function setPrimaryContactAction(): Action
    {
        return Action::make('setPrimaryContact')
            ->label(__('clients.actions.set_primary'))
            ->icon('heroicon-o-star')
            ->color('gray')
            ->size('sm')
            ->action(function (array $arguments): void {
                $contact = $this->ownContact($arguments);

                app(SetPrimaryContactAction::class)->handle($this->linkedClient(), $contact, Auth::user());

                Notification::make()->success()->title(__('clients.portal.notifications.contact_saved'))->send();
            });
    }

    public function removeContactAction(): Action
    {
        return Action::make('removeContact')
            ->label(__('clients.portal.actions.remove_contact'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $contact = $this->ownContact($arguments);

                app(RemoveClientContactAction::class)->handle($contact, Auth::user());

                Notification::make()->success()->title(__('clients.portal.notifications.contact_removed'))->send();
            });
    }

    /**
     * @return array<int, TextInput>
     */
    private function contactFields(): array
    {
        return [
            TextInput::make('name')->label(__('clients.fields.contact_name'))->maxLength(150),
            TextInput::make('role')->label(__('clients.fields.contact_role'))->maxLength(100),
            TextInput::make('phone')->label(__('clients.fields.phone'))->maxLength(30),
            TextInput::make('email')->label(__('clients.fields.email'))->maxLength(150),
        ];
    }

    /** Includes the archived one, so the page can tell it apart from "no client" (RF-58). */
    private function linkedClient(): ?Client
    {
        $clientId = Auth::user()?->client_id;

        return $clientId === null ? null : Client::withTrashed()->find($clientId);
    }

    /** The linked client, after checking the portal ability; aborts with 403 otherwise (RF-62). */
    private function authorizedClient(string $ability): Client
    {
        $client = $this->linkedClient();

        Gate::authorize($ability, $client);

        return $client;
    }

    /**
     * A contact of the linked client, looked up on the server: the id sent by the browser is
     * never trusted to belong to it (RF-62).
     *
     * @param  array<string, mixed>  $arguments
     */
    private function ownContact(array $arguments): ClientContact
    {
        $client = $this->authorizedClient('portal.updateContacts');

        return ClientContact::query()
            ->where('client_id', $client->id)
            ->findOrFail($arguments['contact'] ?? null);
    }

    /** Sends the Actions' validation errors to the modal form of the running action. */
    private function perform(Action $action, callable $operation): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            FormValidation::forAction($exception, $action);
        }
    }
}
