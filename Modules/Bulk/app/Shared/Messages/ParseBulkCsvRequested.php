<?php

namespace Modules\Bulk\Shared\Messages;

use Modules\Bulk\Shared\Messaging\BulkMessaging;

final readonly class ParseBulkCsvRequested extends BulkMessage
{
    public function routingKey(): string
    {
        return BulkMessaging::PARSE_REQUESTED;
    }

    protected static function payloadKey(): string
    {
        return 'bulk_job_id';
    }
}
