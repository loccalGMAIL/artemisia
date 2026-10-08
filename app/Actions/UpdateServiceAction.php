<?php

namespace App\Actions;

use App\Models\Service;
use App\Models\ServicePriceHistory;
use App\Models\User;
use App\Services\ServiceDataValidator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateServiceAction
{
    public function __construct(private readonly ServiceDataValidator $validator) {}

    /**
     * Edits a service (RF-4). A change of the list price is recorded (RF-5) and never touches
     * the copied price of existing budget items, which live in their own table (RF-6).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(Service $service, array $data, User $actor): Service
    {
        $values = $this->validator->validate($data, $service);

        $oldPrice = Money::fromCents(Money::toCents($service->list_price));
        $priceChanged = $oldPrice !== $values['list_price'];

        return DB::transaction(function () use ($service, $values, $oldPrice, $priceChanged, $actor): Service {
            $service->update($values);

            if ($priceChanged) {
                ServicePriceHistory::query()->create([
                    'service_id' => $service->id,
                    'old_price' => $oldPrice,
                    'new_price' => $values['list_price'],
                    'author_id' => $actor->id,
                ]);
            }

            return $service;
        });
    }
}
