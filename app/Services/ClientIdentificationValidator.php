<?php

namespace App\Services;

use App\Enums\ClientType;
use App\Models\Client;
use App\Support\Cuit;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates and normalizes the identification of a client (RF-3, RF-4, RF-6, RNF-1 to RNF-3).
 * Shared by the creation and the edition of a client.
 */
class ClientIdentificationValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{first_name: ?string, last_name: ?string, company_name: ?string, document: string}
     *
     * @throws ValidationException
     */
    public function validate(ClientType $type, array $data, ?Client $editing = null): array
    {
        $document = $this->normalizeDocument((string) ($data['document'] ?? ''));

        $rules = $type === ClientType::Individual
            ? [
                'first_name' => ['required', 'string', 'max:150'],
                'last_name' => ['required', 'string', 'max:150'],
                'document' => ['required', $this->dni(...)],
            ]
            : [
                'company_name' => ['required', 'string', 'max:150'],
                'document' => ['required', $this->cuit(...)],
            ];

        $validated = Validator::make([...$data, 'document' => $document], $rules)->validate();

        $this->assertDocumentIsFree($document, $editing);

        return [
            'first_name' => $type === ClientType::Individual ? trim($validated['first_name']) : null,
            'last_name' => $type === ClientType::Individual ? trim($validated['last_name']) : null,
            'company_name' => $type === ClientType::Company ? trim($validated['company_name']) : null,
            'document' => $document,
        ];
    }

    /** Documents are compared and stored without spaces, dots or hyphens (RF-6). */
    public function normalizeDocument(string $document): string
    {
        return (string) preg_replace('/[\s.\-]/', '', $document);
    }

    private function dni(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^\d{7,8}$/', (string) $value)) {
            $fail(__('clients.validation.invalid_dni'));
        }
    }

    private function cuit(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Cuit::isValid((string) $value)) {
            $fail(__('clients.validation.invalid_cuit'));
        }
    }

    private function assertDocumentIsFree(string $document, ?Client $editing): void
    {
        $existing = Client::withTrashed()
            ->where('document', $document)
            ->when($editing, fn ($query) => $query->whereKeyNot($editing->getKey()))
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'document' => __('clients.validation.document_conflict', [
                    'document' => $document,
                    'name' => $existing->display_name,
                ]),
            ]);
        }
    }
}
