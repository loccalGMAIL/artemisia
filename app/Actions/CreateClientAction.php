<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use App\Services\ClientIdentificationValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateClientAction
{
    public function __construct(private readonly ClientIdentificationValidator $identification) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, User $actor): Client
    {
        Validator::make($data, [
            'person_type' => ['required', Rule::enum(ClientType::class)],
        ])->validate();

        $type = ClientType::from($data['person_type']);
        $identification = $this->identification->validate($type, $data);

        return DB::transaction(function () use ($type, $identification, $actor): Client {
            $client = Client::query()->create([
                ...$identification,
                'person_type' => $type,
                'status' => ClientStatus::Active,
                'created_by' => $actor->id,
            ]);

            ClientHistory::query()->create([
                'client_id' => $client->id,
                'field' => ClientHistoryField::Identification,
                'old_value' => null,
                'new_value' => $client->identificationSnapshot(),
                'author_id' => $actor->id,
            ]);

            return $client;
        });
    }
}
