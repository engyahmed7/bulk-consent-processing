<?php

namespace Modules\Bulk\Processing\Validation;

interface CsvFieldRule
{
    public function field(): string;

    public function required(): bool;

    /**
     * @return list<mixed>
     */
    public function rules(): array;

    public function normalize(mixed $value): mixed;

    public function errorCode(mixed $value): string;
}
