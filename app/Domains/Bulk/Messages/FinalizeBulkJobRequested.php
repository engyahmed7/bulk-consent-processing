<?php

namespace App\Domains\Bulk\Messages;

use App\Domains\Bulk\Messaging\BulkMessaging;

final readonly class FinalizeBulkJobRequested extends BulkMessage
{
    public function routingKey(): string
    {
        return BulkMessaging::FINALIZE_REQUESTED;
    }

    protected static function payloadKey(): string
    {
        return 'bulk_job_id';
    }
}
