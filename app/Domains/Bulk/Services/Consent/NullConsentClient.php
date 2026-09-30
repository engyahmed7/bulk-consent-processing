<?php

namespace App\Domains\Bulk\Services\Consent;

use App\Domains\Bulk\Enums\ConsentAction;

class NullConsentClient implements ConsentClientInterface
{
    public function updateConsent(string $userId, string $phone, ConsentAction $action): ConsentResult
    {
        return ConsentResult::ok();
    }
}
