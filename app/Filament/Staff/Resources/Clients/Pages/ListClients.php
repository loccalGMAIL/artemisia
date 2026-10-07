<?php

namespace App\Filament\Staff\Resources\Clients\Pages;

use App\Actions\ExportClientListAction;
use App\Filament\Staff\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('clients.actions.export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->authorize(fn (): bool => Gate::allows('export', Client::class))
                ->action(fn (): StreamedResponse => app(ExportClientListAction::class)->handle($this->getTableQueryForExport())),
            CreateAction::make(),
        ];
    }
}
