<?php

namespace App\Infrastructure\RabbitMq;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMqConnectionFactory
{
    public function __construct(
        private RabbitMqSettings $settings,
    ) {}

    public function make(): AMQPStreamConnection
    {
        $settings = $this->settings->all();

        return new AMQPStreamConnection(
            host: (string) ($settings['host'] ?? ''),
            port: (int) ($settings['port'] ?? 5672),
            user: (string) ($settings['user'] ?? ''),
            password: (string) ($settings['password'] ?? ''),
            vhost: (string) ($settings['vhost'] ?? '/'),
            heartbeat: (int) ($settings['heartbeat'] ?? 30),
            connection_timeout: (float) ($settings['connection_timeout'] ?? 3.0),
            read_write_timeout: (float) ($settings['read_write_timeout'] ?? 3.0),
        );
    }
}
