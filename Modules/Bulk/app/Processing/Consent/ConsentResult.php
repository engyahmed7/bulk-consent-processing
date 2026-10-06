<?php

namespace Modules\Bulk\Processing\Consent;

final readonly class ConsentResult
{
    public function __construct(
        public bool $success,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    public static function ok(): self
    {
        return new self(true);
    }

    public static function failed(string $code, string $message): self
    {
        return new self(false, $code, $message);
    }
}
