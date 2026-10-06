<?php

namespace Modules\Bulk\Processing\Consent;

use Modules\Bulk\Shared\Enums\ConsentAction;

class NullConsentClient implements ConsentClientInterface
{
    public function updateConsent(string $userId, string $phone, ConsentAction $action): ConsentResult
    {
        return ConsentResult::ok();
    }
}
