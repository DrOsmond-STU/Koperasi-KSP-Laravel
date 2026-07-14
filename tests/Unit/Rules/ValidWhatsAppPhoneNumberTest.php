<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidWhatsAppPhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers 06_TESTING.md NOTIF-09: nomor WhatsApp divalidasi format saat
 * input, bukan baru gagal saat pengiriman.
 */
class ValidWhatsAppPhoneNumberTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_accepts_valid_indonesian_phone_numbers(string $number): void
    {
        $rule = new ValidWhatsAppPhoneNumber;
        $failed = false;

        $rule->validate('phone', $number, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, "Expected {$number} to be valid");
    }

    #[DataProvider('invalidNumbers')]
    public function test_rejects_invalid_phone_numbers(string $number): void
    {
        $rule = new ValidWhatsAppPhoneNumber;
        $failed = false;

        $rule->validate('phone', $number, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, "Expected {$number} to be rejected");
    }

    public static function validNumbers(): array
    {
        return [
            ['08123456789'],
            ['0812345678901'],
            ['628123456789'],
            ['+62 812-3456-789'],
        ];
    }

    public static function invalidNumbers(): array
    {
        return [
            ['12345'],
            ['0212345678'],
            ['abcdefghij'],
            [''],
        ];
    }
}
