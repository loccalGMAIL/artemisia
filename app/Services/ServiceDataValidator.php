<?php

namespace App\Services;

use App\Models\Service;
use App\Support\Money;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates and normalizes the data of a catalog service (RF-1 to RF-4, RNF-1),
 * shared by the creation and the edition of a service.
 */
class ServiceDataValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, description: ?string, work_category_id: int, list_price: string}
     *
     * @throws ValidationException
     */
    public function validate(array $data, ?Service $editing = null): array
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'work_category_id' => ['required', 'integer', 'exists:work_categories,id'],
            'list_price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
        ])->validate();

        $name = trim($validated['name']);

        $this->assertNameIsFree($name, $editing);

        $description = isset($validated['description']) ? trim($validated['description']) : null;

        return [
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'work_category_id' => (int) $validated['work_category_id'],
            'list_price' => Money::fromCents(Money::toCents($validated['list_price'])),
        ];
    }

    /** Names are compared ignoring case and outer spaces, deactivated services included (RF-3). */
    private function assertNameIsFree(string $name, ?Service $editing): void
    {
        $existing = Service::query()
            ->where('name_normalized', mb_strtolower($name))
            ->when($editing, fn ($query) => $query->whereKeyNot($editing->getKey()))
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'name' => __('budgets.validation.service_name_conflict', ['name' => $existing->name]),
            ]);
        }
    }
}
