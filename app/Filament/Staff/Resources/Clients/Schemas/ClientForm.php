<?php

namespace App\Filament\Staff\Resources\Clients\Schemas;

use App\Enums\ClientType;
use App\Models\Province;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('clients.sections.identification'))->schema([
                Select::make('person_type')
                    ->label(__('clients.fields.person_type'))
                    ->options(collect(ClientType::cases())->mapWithKeys(fn (ClientType $type) => [$type->value => $type->label()])->all())
                    ->required()
                    ->live()
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                TextInput::make('first_name')
                    ->label(__('clients.fields.first_name'))
                    ->visible(fn (Get $get): bool => $get('person_type') === ClientType::Individual->value)
                    ->maxLength(150),
                TextInput::make('last_name')
                    ->label(__('clients.fields.last_name'))
                    ->visible(fn (Get $get): bool => $get('person_type') === ClientType::Individual->value)
                    ->maxLength(150),
                TextInput::make('company_name')
                    ->label(__('clients.fields.company_name'))
                    ->visible(fn (Get $get): bool => $get('person_type') === ClientType::Company->value)
                    ->maxLength(150),
                TextInput::make('document')
                    ->label(fn (Get $get): string => ClientType::tryFrom((string) $get('person_type'))?->documentLabel() ?? __('clients.fields.document')),
            ])->columns(2),

            Section::make(__('clients.sections.address'))
                ->visibleOn('edit')
                ->schema([
                    TextInput::make('street')->label(__('clients.fields.street'))->maxLength(150),
                    TextInput::make('street_number')->label(__('clients.fields.street_number'))->maxLength(20),
                    TextInput::make('city')->label(__('clients.fields.city'))->maxLength(100),
                    Select::make('province_id')
                        ->label(__('clients.fields.province'))
                        ->options(fn (): array => Province::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),
                    TextInput::make('postal_code')->label(__('clients.fields.postal_code'))->maxLength(15),
                ])->columns(2),
        ]);
    }
}
