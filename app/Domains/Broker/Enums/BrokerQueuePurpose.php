<?php

namespace App\Domains\Broker\Enums;

enum BrokerQueuePurpose: string
{
    case BulkParse = 'bulk_parse';
    case BulkValidate = 'bulk_validate';
    case BulkFinalize = 'bulk_finalize';
    case BulkDlq = 'bulk_dlq';
}
