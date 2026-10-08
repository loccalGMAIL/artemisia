<?php

namespace App\Filament\Staff\Resources\Services\Schemas;

use App\Models\WorkCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('budgets.services.fields.name'))
                ->maxLength(150),
            Select::make('work_category_id')
                ->label(__('budgets.services.fields.category'))
                ->options(fn (): array => WorkCategory::query()->orderBy('name')->pluck('name', 'id')->all()),
            TextInput::make('list_price')
                ->label(__('budgets.services.fields.list_price'))
                ->prefix('$'),
            Textarea::make('description')
                ->label(__('budgets.services.fields.description'))
                ->columnSpanFull(),
        ])->columns(2);
    }
}
