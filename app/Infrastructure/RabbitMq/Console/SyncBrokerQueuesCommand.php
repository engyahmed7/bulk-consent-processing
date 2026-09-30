<?php

namespace App\Infrastructure\RabbitMq\Console;

use App\Domains\Broker\Services\BrokerQueueSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncBrokerQueuesCommand extends Command
{
    protected $signature = 'bulk:sync-queues';

    protected $description = 'Sync RabbitMQ dashboard queues into the broker_queues database table';

    public function handle(BrokerQueueSyncService $syncService): int
    {
        try {
            $result = $syncService->sync();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Discovered '.$result['discovered'].' queue(s) from RabbitMQ.');

        if ($result['synced'] === []) {
            $this->warn('No configured broker queues were found in RabbitMQ. Register the purpose-to-queue mappings first.');
        } else {
            $this->info('Synced: '.implode(', ', $result['synced']));
        }

        if ($result['deactivated'] !== []) {
            $this->warn('Deactivated missing purposes: '.implode(', ', $result['deactivated']));
        }

        return self::SUCCESS;
    }
}
