<?php

namespace Modules\Bulk\Processing\Validation\Rules;

use Modules\Bulk\Processing\Validation\CsvFieldRule;

class CountryFieldRule implements CsvFieldRule
{
    public function field(): string
    {
        return 'country';
    }

    public function required(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'nullable',
            'string',
            'size:2',
        ];
    }

    public function normalize(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }

    public function errorCode(mixed $value): string
    {
        return 'invalid_country';
    }
}