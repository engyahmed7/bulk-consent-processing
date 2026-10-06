<?php

namespace Modules\Bulk\Shared\Enums;

enum BulkJobStatus: string
{
    case Queued = 'queued';
    case Parsing = 'parsing';
    case Processing = 'processing';
    case Finalizing = 'finalizing';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';
}
