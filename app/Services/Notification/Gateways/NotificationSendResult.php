<?php

namespace App\Services\Notification\Gateways;

readonly class NotificationSendResult
{
    public function __construct(
        public bool $success,
        public ?string $cost,
        public ?string $errorMessage,
    ) {}
}
