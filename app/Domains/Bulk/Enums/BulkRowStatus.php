<?php

namespace App\Domains\Bulk\Enums;

enum BulkRowStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
