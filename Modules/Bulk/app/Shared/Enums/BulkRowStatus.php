<?php

namespace Modules\Bulk\Shared\Enums;

enum BulkRowStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
