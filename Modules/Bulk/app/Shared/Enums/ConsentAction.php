<?php

namespace Modules\Bulk\Shared\Enums;

enum ConsentAction: string
{
    case OptIn = 'opt_in';
    case OptOut = 'opt_out';
}
