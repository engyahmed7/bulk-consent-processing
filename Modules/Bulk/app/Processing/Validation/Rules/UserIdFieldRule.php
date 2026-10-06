<?php

namespace Modules\Bulk\Processing\Validation\Rules;

use Modules\Bulk\Processing\Validation\CsvFieldRule;

class UserIdFieldRule implements CsvFieldRule
{
    public function field(): string
    {
        return 'userid';
    }

    public function required(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['required', 'string'];
    }

    public function normalize(mixed $value): string
    {
        return trim((string) $value);
    }

    public function errorCode(mixed $value): string
    {
        return 'missing_userid';
    }
}
