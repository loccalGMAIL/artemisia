<?php

namespace App\Actions;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportClientListAction
{
    /**
     * Streams the clients of the given query as a CSV, in the query's own order (RF-56).
     * The query is the one the list shows, so search, filters and order are the user's.
     * No export library is needed: native CSV functions are enough (plan D-7).
     *
     * @param  Builder<Client>  $query
     */
    public function handle(Builder $query): StreamedResponse
    {
        $filename = 'clientes-'.now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');

            // UTF-8 BOM, so spreadsheets read accents correctly.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                __('clients.fields.display_name'),
                __('clients.fields.person_type'),
                __('clients.fields.document'),
                __('clients.fields.status'),
                __('clients.fields.created_at'),
            ], escape: '');

            foreach ($query->cursor() as $client) {
                fputcsv($output, [
                    $this->safe($client->display_name),
                    $client->person_type->label(),
                    $client->document,
                    $client->status->label(),
                    $client->created_at->format('d/m/Y H:i'),
                ], escape: '');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Names are typed by people: a cell starting with = + - or @ would run as a formula
     * when the file is opened in a spreadsheet, so it is prefixed with an apostrophe.
     */
    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : $value;
    }
}
