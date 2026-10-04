<?php

namespace App\Domains\Bulk\Services\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RowValidator
{
    /**
     * Validate a single row of the bulk CSV.
     */
    public function validate(?string $userId, ?string $phoneNumber): RowValidationResult
    {
        $data = [
            'userid' => $userId,
            'phonenumber' => $phoneNumber,
        ];

        $rules = config('bulk.validation_rules', []);

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $firstErrorKey = $errors->first() ? array_key_first($errors->getMessages()) : 'general';
            $errorMessage = $errors->first();

            return RowValidationResult::invalid(
                (string) $userId,
                (string) $phoneNumber,
                $this->mapErrorCode($firstErrorKey),
                $errorMessage
            );
        }

        // Normalize the phone number for the OK result
        $normalizedPhone = preg_replace('/[\s\-()]/', '', (string) $phoneNumber);

        return RowValidationResult::ok((string) $userId, $normalizedPhone);
    }

    /**
     * Map Laravel validation keys to existing bulk error codes.
     */
    protected function mapErrorCode(string $key): string
    {
        return match ($key) {
            'userid' => 'missing_userid',
            'phonenumber' => 'missing_phonenumber',
            default => 'invalid_data',
        };
    }
}
