<?php

namespace Modules\Core\Kernel;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('Modules/Core/config/config.php'), 'core');

        foreach (config('core.features', []) as $featureProvider) {
            if (! is_string($featureProvider) || ! is_subclass_of($featureProvider, ServiceProvider::class)) {
                throw new InvalidArgumentException('Every Core feature must be a Laravel service provider class.');
            }

            $this->app->register($featureProvider);
        }
    }
}
