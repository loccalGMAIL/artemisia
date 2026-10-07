<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateClientAddressAction
{
    private const FIELDS = ['street', 'street_number', 'city', 'province_id', 'postal_code'];

    /**
     * Replaces the client's single address. Every part is optional (RF-9, RF-10).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(Client $client, array $data, User $actor): Client
    {
        $validated = Validator::make($data, [
            'street' => ['nullable', 'string', 'max:150'],
            'street_number' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'postal_code' => ['nullable', 'string', 'max:15'],
        ])->validate();

        $before = $client->addressSnapshot();

        foreach (self::FIELDS as $field) {
            $value = $validated[$field] ?? null;

            $value = $value === '' ? null : $value;

            $client->{$field} = ($field === 'province_id' && $value !== null) ? (int) $value : $value;
        }

        if ($client->addressSnapshot() === $before) {
            return $client;
        }

        return DB::transaction(function () use ($client, $before, $actor): Client {
            $client->save();

            ClientHistory::query()->create([
                'client_id' => $client->id,
                'field' => ClientHistoryField::Address,
                'old_value' => $before,
                'new_value' => $client->addressSnapshot(),
                'author_id' => $actor->id,
            ]);

            return $client;
        });
    }
}
