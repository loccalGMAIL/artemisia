<?php

namespace App\Actions;

use App\Models\Budget;

class BuildWhatsAppLinkAction
{
    /** Shortest phone, in digits, that can still be a real number. */
    private const MIN_DIGITS = 8;

    private const COUNTRY_PREFIX = '54';

    private const MOBILE_PREFIX = '549';

    /**
     * The wa.me link that opens a WhatsApp chat with the client's contact phone, with a
     * preloaded text that names the budget (RF-68). It is only a link: no API, nothing is
     * attached (RF-70). Null when the client has no usable phone (RF-69).
     */
    public function handle(Budget $budget): ?string
    {
        $client = $budget->client;
        $number = $this->internationalNumber($client->contactPhone());

        if ($number === null) {
            return null;
        }

        $message = __('budgets.whatsapp.message', [
            'client' => $client->display_name,
            'id' => $budget->id,
            'title' => $budget->title,
        ]);

        return "https://wa.me/{$number}?text=".rawurlencode($message);
    }

    /**
     * Digits only, with the country code. Every client operates in Argentina (spec 003,
     * section 9), so a local number gets 54 9, the prefix of an Argentine mobile line.
     */
    private function internationalNumber(?string $phone): ?string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $phone);
        $digits = ltrim($digits, '0');

        if (strlen($digits) < self::MIN_DIGITS) {
            return null;
        }

        return str_starts_with($digits, self::COUNTRY_PREFIX) ? $digits : self::MOBILE_PREFIX.$digits;
    }
}
