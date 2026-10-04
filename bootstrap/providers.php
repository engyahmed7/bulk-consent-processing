<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use Modules\Core\Kernel\CoreServiceProvider;

return [
    CoreServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    App\Providers\ValidationServiceProvider::class,
];
