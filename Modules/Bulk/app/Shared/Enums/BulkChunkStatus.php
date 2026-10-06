<?php

namespace Modules\Bulk\Shared\Enums;

enum BulkChunkStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
