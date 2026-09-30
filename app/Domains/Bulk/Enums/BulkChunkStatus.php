<?php

namespace App\Domains\Bulk\Enums;

enum BulkChunkStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
