<?php

namespace Tests\Unit;

use App\Domains\Bulk\Services\Validation\RowValidator;
use Tests\TestCase;

class RowValidatorTest extends TestCase
{
    public function test_row_validator_accepts_valid_phone(): void
    {
        $result = app(RowValidator::class)->validate('user-1', '+966501234567');

        $this->assertTrue($result->valid);
        $this->assertSame('+966501234567', $result->phoneNumber);
    }

    public function test_row_validator_rejects_empty_userid(): void
    {
        $result = app(RowValidator::class)->validate(' ', '966501234567');

        $this->assertFalse($result->valid);
        $this->assertSame('missing_userid', $result->errorCode);
    }
}
