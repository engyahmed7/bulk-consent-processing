<?php

namespace Modules\Bulk\Processing\Consent;

use Modules\Bulk\Shared\Enums\ConsentAction;

interface ConsentClientInterface
{
    public function updateConsent(string $userId, string $phone, ConsentAction $action): ConsentResult;
}
