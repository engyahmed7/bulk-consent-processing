<?php

namespace Modules\Bulk\Providers;

use Modules\Bulk\Console\EnsureWormBucketCommand;
use Modules\Bulk\Processing\Consent\ConsentClientInterface;
use Modules\Bulk\Processing\Consent\HttpConsentClient;
use Modules\Bulk\Processing\Consent\NullConsentClient;
use Modules\Bulk\Processing\Validation\CsvFieldRuleRegistry;
use Modules\Bulk\Shared\Messaging\BulkMessaging;
use Modules\Core\Kernel\Support\ModuleServiceProvider;

class BulkServiceProvider extends ModuleServiceProvider
{
    protected ?string $messaging = BulkMessaging::class;

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(base_path('Modules/Bulk/config/bulk.php'), 'bulk');
        $this->app->singleton(CsvFieldRuleRegistry::class);
        $this->app->bind(ConsentClientInterface::class, function (): ConsentClientInterface {
            $baseUrl = (string) config('consent.base_url');

            if ($baseUrl === '' || app()->environment('testing')) {
                return new NullConsentClient;
            }

            return new HttpConsentClient;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(base_path('Modules/Bulk/database/migrations'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                EnsureWormBucketCommand::class,
            ]);
        }
    }
}
