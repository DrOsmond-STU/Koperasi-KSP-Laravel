<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Covers 06_TESTING.md NOTIF-09: nomor WhatsApp anggota divalidasi format
 * saat input/update data anggota (PRD §16), not left to fail only when a
 * notification is actually sent. Accepts Indonesian mobile numbers in
 * either local (08xxxxxxxxxx) or international (62xxxxxxxxxx) form.
 */
class ValidWhatsAppPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digitsOnly = preg_replace('/[^0-9]/', '', (string) $value);

        if (! preg_match('/^(62|0)8[0-9]{8,12}$/', $digitsOnly)) {
            $fail('Nomor WhatsApp tidak valid — gunakan format 08xxxxxxxxxx atau 62xxxxxxxxxx.');
        }
    }
}
