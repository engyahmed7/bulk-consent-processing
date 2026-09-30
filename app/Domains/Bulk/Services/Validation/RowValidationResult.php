<?php

namespace App\Domains\Bulk\Services\Validation;

final readonly class RowValidationResult
{
    public function __construct(
        public bool $valid,
        public string $userId,
        public string $phoneNumber,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    public static function ok(string $userId, string $phoneNumber): self
    {
        return new self(true, $userId, $phoneNumber);
    }

    public static function invalid(string $userId, string $phoneNumber, string $code, string $message): self
    {
        return new self(false, $userId, $phoneNumber, $code, $message);
    }
}
