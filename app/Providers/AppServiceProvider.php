<?php

namespace App\Providers;

use App\Domains\Bulk\Services\Consent\ConsentClientInterface;
use App\Domains\Bulk\Services\Consent\HttpConsentClient;
use App\Domains\Bulk\Services\Consent\NullConsentClient;
use App\Infrastructure\RabbitMq\Console\ConsumeByPurposeCommand;
use App\Infrastructure\RabbitMq\Console\SyncBrokerQueuesCommand;
use App\Infrastructure\Storage\Console\EnsureWormBucketCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
                ConsumeByPurposeCommand::class,
                SyncBrokerQueuesCommand::class,
                EnsureWormBucketCommand::class,
            ]);
        }
    }
}
