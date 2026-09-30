<?php

namespace App\Domains\Broker\Services;

use App\Domains\Broker\Models\BrokerQueue;
use App\Infrastructure\RabbitMq\RabbitMqSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BrokerQueueSyncService
{
    public function __construct(
        private RabbitMqSettings $settings,
    ) {}

    /**
     * Activate configured broker queues found in RabbitMQ without deactivating mappings that were not returned.
     *
     * @return array{synced: list<string>, deactivated: list<string>, discovered: int}
     */
    public function sync(): array
    {
        $queues = $this->fetchQueuesFromManagementApi();
        $vhost = (string) ($this->settings->all()['vhost'] ?? '/');
        $configuredQueues = BrokerQueue::query()->get();
        $discoveredNames = [];

        foreach ($queues as $queue) {
            $name = (string) ($queue['name'] ?? '');
            $queueVhost = (string) ($queue['vhost'] ?? '/');

            if ($name === '' || $queueVhost !== $vhost || ! $configuredQueues->contains('queue_name', $name)) {
                continue;
            }

            $discoveredNames[$name] = true;
        }

        $synced = [];

        foreach ($configuredQueues as $brokerQueue) {
            if (isset($discoveredNames[$brokerQueue->queue_name])) {
                if (! $brokerQueue->is_active) {
                    $brokerQueue->update(['is_active' => true]);
                }

                $synced[] = $brokerQueue->queue_name;
            }
        }

        return [
            'synced' => $synced,
            'deactivated' => [],
            'discovered' => count($queues),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchQueuesFromManagementApi(): array
    {
        $settings = $this->settings->all();
        $baseUrl = rtrim((string) ($settings['management_url'] ?? ''), '/');
        $user = (string) ($settings['management_user'] ?? '');
        $password = (string) ($settings['management_password'] ?? '');

        $response = Http::withBasicAuth($user, $password)
            ->acceptJson()
            ->timeout(10)
            ->get("{$baseUrl}/api/queues");

        if (! $response->successful()) {
            throw new RuntimeException(
                "Unable to sync queues from RabbitMQ management API ({$response->status()}): {$response->body()}"
            );
        }

        /** @var list<array<string, mixed>> $queues */
        $queues = $response->json();

        return is_array($queues) ? $queues : [];
    }
}
