<?php

namespace App\Actions;

use App\Models\Service;
use App\Models\ServicePriceHistory;
use App\Models\User;
use App\Services\ServiceDataValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateServiceAction
{
    public function __construct(private readonly ServiceDataValidator $validator) {}

    /**
     * Creates an active service and records its first list price (RF-1, RF-5).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, User $actor): Service
    {
        $values = $this->validator->validate($data);

        return DB::transaction(function () use ($values, $actor): Service {
            $service = Service::query()->create([...$values, 'is_active' => true]);

            ServicePriceHistory::query()->create([
                'service_id' => $service->id,
                'old_price' => null,
                'new_price' => $values['list_price'],
                'author_id' => $actor->id,
            ]);

            return $service;
        });
    }
}
