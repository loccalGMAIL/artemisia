<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates the data of a client contact (RF-13, RF-15), shared by add and update.
 */
class ClientContactValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, role: ?string, phone: ?string, email: ?string}
     *
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'role' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
        ])->validate();

        $contact = [
            'name' => trim($validated['name']),
            'role' => $this->blankToNull($validated['role'] ?? null),
            'phone' => $this->blankToNull($validated['phone'] ?? null),
            'email' => $this->blankToNull($validated['email'] ?? null),
        ];

        if ($contact['phone'] === null && $contact['email'] === null) {
            throw ValidationException::withMessages([
                'phone' => __('clients.validation.contact_needs_phone_or_email'),
            ]);
        }

        return $contact;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
