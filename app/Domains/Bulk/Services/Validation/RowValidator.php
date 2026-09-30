<?php

namespace App\Domains\Bulk\Services\Validation;

class RowValidator
{
    public function validate(?string $userId, ?string $phoneNumber): RowValidationResult
    {
        $userId = trim((string) $userId);
        $phoneNumber = trim((string) $phoneNumber);

        if ($userId === '') {
            return RowValidationResult::invalid($userId, $phoneNumber, 'missing_userid', 'userid is required.');
        }

        if ($phoneNumber === '') {
            return RowValidationResult::invalid($userId, $phoneNumber, 'missing_phonenumber', 'phonenumber is required.');
        }

        $normalizedPhone = preg_replace('/[\s\-()]/', '', $phoneNumber) ?? $phoneNumber;

        if (! preg_match('/^\+?[0-9]{8,15}$/', $normalizedPhone)) {
            return RowValidationResult::invalid($userId, $phoneNumber, 'invalid_phonenumber', 'phonenumber format is invalid.');
        }

        return RowValidationResult::ok($userId, $normalizedPhone);
    }
}
