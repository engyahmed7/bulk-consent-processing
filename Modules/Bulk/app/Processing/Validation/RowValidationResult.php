<?php

namespace Modules\Bulk\Processing\Validation;

final readonly class RowValidationResult
{
    public function __construct(
        public bool $valid,
        public array $fields,
        public ?string $errorField = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function ok(array $fields): self
    {
        return new self(true, $fields);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function invalid(array $fields, string $field, string $code, string $message): self
    {
        return new self(false, $fields, $field, $code, $message);
    }

    public function userId(): string
    {
        return (string) ($this->fields['userid'] ?? '');
    }

    public function phoneNumber(): string
    {
        return (string) ($this->fields['phonenumber'] ?? '');
    }
}
