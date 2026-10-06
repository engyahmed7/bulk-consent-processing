<?php

namespace Tests\Unit;

use Modules\Bulk\Processing\Validation\RowValidator;
use Tests\TestCase;

class RowValidatorTest extends TestCase
{
    public function test_row_validator_normalizes_valid_fields(): void
    {
        $result = app(RowValidator::class)->validate([
            'userid' => ' user-1 ',
            'phonenumber' => '+966 (50) 123-4567',
            'email' => ' user@example.test ',
        ]);

        $this->assertTrue($result->valid);
        $this->assertSame('user-1', $result->fields['userid']);
        $this->assertSame('+966501234567', $result->fields['phonenumber']);
        $this->assertSame('user@example.test', $result->fields['email']);
    }

    public function test_row_validator_rejects_empty_userid(): void
    {
        $result = app(RowValidator::class)->validate([
            'userid' => ' ',
            'phonenumber' => '966501234567',
        ]);

        $this->assertFalse($result->valid);
        $this->assertSame('missing_userid', $result->errorCode);
        $this->assertSame('userid', $result->errorField);
    }

    public function test_row_validator_applies_a_discovered_rule_to_an_optional_csv_column(): void
    {
        $result = app(RowValidator::class)->validate([
            'userid' => 'user-1',
            'phonenumber' => '966501234567',
            'email' => 'not-an-email',
        ]);

        $this->assertFalse($result->valid);
        $this->assertSame('email', $result->errorField);
        $this->assertSame('invalid_email', $result->errorCode);
    }
}
