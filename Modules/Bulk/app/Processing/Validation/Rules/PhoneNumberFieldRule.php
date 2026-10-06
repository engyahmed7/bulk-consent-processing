<?php

namespace Modules\Bulk\Processing\Validation\Rules;

use Modules\Bulk\Processing\Validation\CsvFieldRule;

class PhoneNumberFieldRule implements CsvFieldRule
{
    public function field(): string
    {
        return 'phonenumber';
    }

    public function required(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['required', 'regex:/^\\+?[0-9]{8,15}$/'];
    }

    public function normalize(mixed $value): string
    {
        return preg_replace('/[\s\-()]/', '', trim((string) $value)) ?? trim((string) $value);
    }

    public function errorCode(mixed $value): string
    {
        return trim((string) $value) === '' ? 'missing_phonenumber' : 'invalid_phonenumber';
    }
}
