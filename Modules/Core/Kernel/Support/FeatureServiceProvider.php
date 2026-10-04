<?php

namespace Modules\Core\Kernel\Support;

use Illuminate\Support\Facades\File;
use LogicException;
use ReflectionClass;

abstract class FeatureServiceProvider extends ModuleServiceProvider
{
    abstract protected function key(): string;

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(
            base_path('config/'.$this->key().'.php'),
            'core.'.$this->key(),
        );
    }

    public function boot(): void
    {
        $providerFile = (new ReflectionClass($this))->getFileName();

        if (! is_string($providerFile)) {
            throw new LogicException('A feature service provider must be loaded from a file.');
        }

        $migrationsPath = dirname($providerFile).'/database/migrations';

        if (File::isDirectory($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }
}
