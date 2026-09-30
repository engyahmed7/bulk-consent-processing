<?php

namespace App\Domains\Bulk\Enums;

enum ConsentAction: string
{
    case OptIn = 'opt_in';
    case OptOut = 'opt_out';
}
