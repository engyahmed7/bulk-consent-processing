<?php

namespace Tests\Unit;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Broker\Models\BrokerQueue;
use App\Domains\Broker\Services\BrokerQueueSyncService;
use App\Infrastructure\RabbitMq\RabbitMqSettings;
use App\Infrastructure\Settings\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrokerQueueSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_uses_database_queue_names_and_rabbitmq_connection_settings(): void
    {
        BrokerQueue::query()->create([
            'purpose' => BrokerQueuePurpose::BulkParse,
            'queue_name' => 'ops.import.parse.v3',
            'is_active' => true,
        ]);
        BrokerQueue::query()->create([
            'purpose' => BrokerQueuePurpose::BulkValidate,
            'queue_name' => 'ops.import.validate.v3',
            'is_active' => true,
        ]);
        Setting::query()->create([
            'setting_group' => RabbitMqSettings::GROUP,
            'setting_key' => 'management_url',
            'setting_value' => 'http://rabbit-admin.internal:15672',
        ]);
        Setting::query()->create([
            'setting_group' => RabbitMqSettings::GROUP,
            'setting_key' => 'vhost',
            'setting_value' => 'bulk-vhost',
        ]);
        Setting::query()->create([
            'setting_group' => RabbitMqSettings::GROUP,
            'setting_key' => 'host',
            'setting_value' => 'rabbit.internal',
        ]);
        Setting::query()->create([
            'setting_group' => RabbitMqSettings::GROUP,
            'setting_key' => 'port',
            'setting_value' => '5678',
        ]);

        Http::fake([
            'http://rabbit-admin.internal:15672/api/queues' => Http::response([
                ['name' => 'ops.import.parse.v3', 'vhost' => 'bulk-vhost'],
                ['name' => 'ops.import.validate.v3', 'vhost' => 'bulk-vhost'],
                ['name' => 'unrelated.queue', 'vhost' => 'bulk-vhost'],
                ['name' => 'ops.import.parse.v3', 'vhost' => '/'],
            ], 200),
        ]);

        $result = app(BrokerQueueSyncService::class)->sync();
        $rabbitSettings = app(RabbitMqSettings::class)->all();

        $this->assertSame(['ops.import.parse.v3', 'ops.import.validate.v3'], $result['synced']);
        $this->assertSame('rabbit.internal', $rabbitSettings['host']);
        $this->assertSame('5678', $rabbitSettings['port']);
        $this->assertDatabaseHas('broker_queues', [
            'purpose' => BrokerQueuePurpose::BulkParse->value,
            'queue_name' => 'ops.import.parse.v3',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('broker_queues', [
            'queue_name' => 'unrelated.queue',
        ]);
    }

    public function test_sync_does_not_deactivate_database_mapping_when_management_api_omits_it(): void
    {
        BrokerQueue::query()->create([
            'purpose' => BrokerQueuePurpose::BulkFinalize,
            'queue_name' => 'jobs.finalize.v2',
            'is_active' => true,
        ]);

        Http::fake([
            'http://127.0.0.1:15672/api/queues' => Http::response([
                ['name' => 'incoming.parse.v2', 'vhost' => '/'],
            ], 200),
        ]);

        $result = app(BrokerQueueSyncService::class)->sync();

        $this->assertSame([], $result['deactivated']);
        $this->assertDatabaseHas('broker_queues', [
            'purpose' => BrokerQueuePurpose::BulkFinalize->value,
            'is_active' => true,
        ]);
    }
}
