<?php

use App\Providers\Filament\AdminPanelProvider;
use Modules\Bulk\Providers\BulkServiceProvider;
use Modules\Core\Kernel\CoreServiceProvider;

return [
    CoreServiceProvider::class,
    BulkServiceProvider::class,
    AdminPanelProvider::class,
];
