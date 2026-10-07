<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Enums\ClientType;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use App\Services\ClientIdentificationValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateClientIdentificationAction
{
    public function __construct(private readonly ClientIdentificationValidator $identification) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(Client $client, array $data, User $actor): Client
    {
        $requestedType = ClientType::tryFrom((string) ($data['person_type'] ?? $client->person_type->value));

        if ($requestedType !== $client->person_type) {
            throw ValidationException::withMessages([
                'person_type' => __('clients.validation.person_type_immutable'),
            ]);
        }

        $values = $this->identification->validate($client->person_type, $data, $client);

        $before = $client->identificationSnapshot();

        $client->fill($values);

        if (! $client->isDirty()) {
            return $client;
        }

        return DB::transaction(function () use ($client, $before, $actor): Client {
            $client->save();

            ClientHistory::query()->create([
                'client_id' => $client->id,
                'field' => ClientHistoryField::Identification,
                'old_value' => $before,
                'new_value' => $client->identificationSnapshot(),
                'author_id' => $actor->id,
            ]);

            return $client;
        });
    }
}
