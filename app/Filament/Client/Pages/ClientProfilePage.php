<?php

namespace App\Filament\Client\Pages;

use App\Models\Client;
use BackedEnum;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Home of the client portal: the record card of the client the signed-in account is linked
 * to (RF-57). The client is never taken from the request, only from the account (RF-62).
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

            Section::make(__('clients.relations.contacts'))->schema([
                RepeatableEntry::make('contacts')
                    ->hiddenLabel()
                    ->state($client->contacts()->orderByDesc('is_primary')->orderBy('id')->get()->map(fn ($contact): array => [
                        'name' => $contact->name,
                        'role' => $contact->role,
                        'phone' => $contact->phone,
                        'email' => $contact->email,
                        'primary' => $contact->is_primary ? __('clients.portal.primary_contact') : null,
                    ])->all())
                    ->placeholder(__('clients.portal.no_contacts'))
                    ->schema([
                        TextEntry::make('name')->label(__('clients.fields.contact_name')),
                        TextEntry::make('primary')->hiddenLabel()->badge()->placeholder(''),
                        TextEntry::make('role')->label(__('clients.fields.contact_role'))->placeholder('—'),
                        TextEntry::make('phone')->label(__('clients.fields.phone'))->placeholder('—'),
                        TextEntry::make('email')->label(__('clients.fields.email'))->placeholder('—'),
                    ])
                    ->columns(5),
            ]),
        ]);
    }

    /** Includes the archived one, so the page can tell it apart from "no client" (RF-58). */
    private function linkedClient(): ?Client
    {
        $clientId = Auth::user()?->client_id;

        return $clientId === null ? null : Client::withTrashed()->find($clientId);
    }
}
