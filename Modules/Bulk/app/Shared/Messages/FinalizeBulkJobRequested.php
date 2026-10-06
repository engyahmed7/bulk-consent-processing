<?php

namespace Modules\Bulk\Shared\Messages;

use Modules\Bulk\Shared\Messaging\BulkMessaging;

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
