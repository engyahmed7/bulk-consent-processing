<?php

namespace App\Domains\Bulk\Messages;

use App\Domains\Bulk\Messaging\BulkMessaging;

final readonly class ProcessBulkChunkRequested extends BulkMessage
{
    public function routingKey(): string
    {
        return BulkMessaging::CHUNK_REQUESTED;
    }

    protected static function payloadKey(): string
    {
        return 'chunk_id';
    }
}
