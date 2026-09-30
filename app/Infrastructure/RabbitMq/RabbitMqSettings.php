<?php

namespace App\Infrastructure\RabbitMq;

use App\Infrastructure\Settings\SettingRepository;

class RabbitMqSettings
{
    public const GROUP = 'rabbitmq_group';

    public function __construct(
        private SettingRepository $settings,
    ) {}

    /**
     * Database settings override config defaults; the latter support initial setup and local development.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_replace(
            (array) config('rabbitmq', []),
            $this->settings->valuesForGroup(self::GROUP),
        );
    }

    public function defaultExchange(): string
    {
        return (string) ($this->all()['default_exchange'] ?? '');
    }

    public function integer(string $key): int
    {
        return (int) ($this->all()[$key] ?? 0);
    }
}
