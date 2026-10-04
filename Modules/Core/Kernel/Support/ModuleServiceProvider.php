<?php

namespace Modules\Core\Kernel\Support;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Modules\Core\Features\RabbitMQ\Contracts\ModuleMessaging;

abstract class ModuleServiceProvider extends ServiceProvider
{
    protected ?string $messaging = null;

    public function register(): void
    {
        if ($this->messaging === null) {
            return;
        }

        if (! is_subclass_of($this->messaging, ModuleMessaging::class)) {
            throw new InvalidArgumentException('The messaging declaration must implement '.ModuleMessaging::class.'.');
        }

        $this->app->singleton($this->messaging);
        $this->app->tag($this->messaging, ContainerTags::MESSAGING);
    }
}
