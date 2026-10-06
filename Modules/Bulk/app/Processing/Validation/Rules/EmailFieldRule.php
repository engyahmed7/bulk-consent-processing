<?php

namespace Modules\Bulk\Processing\Validation\Rules;

use Modules\Bulk\Processing\Validation\CsvFieldRule;

class EmailFieldRule implements CsvFieldRule
{
    public function field(): string
    {
        return 'email';
    }

    public function required(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return ['sometimes', 'nullable', 'email'];
    }

    public function normalize(mixed $value): string
    {
        return trim((string) $value);
    }

    public function errorCode(mixed $value): string
    {
        return 'invalid_email';
    }
}
