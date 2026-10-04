<?php

namespace App\Providers;

use App\Domains\Bulk\Messaging\BulkMessaging;
use App\Domains\Bulk\Services\Consent\ConsentClientInterface;
use App\Domains\Bulk\Services\Consent\HttpConsentClient;
use App\Domains\Bulk\Services\Consent\NullConsentClient;
use App\Infrastructure\Storage\Console\EnsureWormBucketCommand;
use Modules\Core\Kernel\Support\ModuleServiceProvider;

class AppServiceProvider extends ModuleServiceProvider
{
    protected ?string $messaging = BulkMessaging::class;

    public function register(): void
    {
        parent::register();

        $this->app->bind(ConsentClientInterface::class, function () {
            $baseUrl = (string) config('consent.base_url');

            if ($baseUrl === '' || app()->environment('testing')) {
                return new NullConsentClient;
            }

            return new HttpConsentClient;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                EnsureWormBucketCommand::class,
            ]);
        }
    }
}
