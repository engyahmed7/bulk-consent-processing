<?php

namespace Tests\Unit;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Broker\Exceptions\BrokerQueueNotConfiguredException;
use App\Domains\Broker\Models\BrokerQueue;
use App\Domains\Broker\Services\QueueResolver;
use App\Domains\Bulk\Services\Validation\RowValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QueueResolverAndValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_resolver_returns_active_queue_from_database(): void
    {
        BrokerQueue::query()->create([
            'purpose' => BrokerQueuePurpose::BulkValidate,
            'queue_name' => 'dashboard.validate.q',
            'is_active' => true,
        ]);

        $resolved = app(QueueResolver::class)->resolve(BrokerQueuePurpose::BulkValidate);

        $this->assertSame('dashboard.validate.q', $resolved->queue_name);
    }

    public function test_queue_resolver_ignores_a_stale_cached_queue_mapping(): void
    {
        BrokerQueue::query()->create([
            'purpose' => BrokerQueuePurpose::BulkValidate,
            'queue_name' => 'current.validate.q',
            'is_active' => true,
        ]);
        Cache::put('broker_queue:bulk_validate', new BrokerQueue([
            'purpose' => BrokerQueuePurpose::BulkValidate,
            'queue_name' => 'stale.validate.q',
            'is_active' => false,
        ]));

        $resolved = app(QueueResolver::class)->resolve(BrokerQueuePurpose::BulkValidate);

        $this->assertSame('current.validate.q', $resolved->queue_name);
    }

    public function test_queue_resolver_fails_when_missing(): void
    {
        $this->expectException(BrokerQueueNotConfiguredException::class);

        app(QueueResolver::class)->resolve(BrokerQueuePurpose::BulkParse);
    }

    public function test_row_validator_accepts_valid_phone(): void
    {
        $result = app(RowValidator::class)->validate('user-1', '+966501234567');

        $this->assertTrue($result->valid);
        $this->assertSame('+966501234567', $result->phoneNumber);
    }

    public function test_row_validator_rejects_empty_userid(): void
    {
        $result = app(RowValidator::class)->validate(' ', '966501234567');

        $this->assertFalse($result->valid);
        $this->assertSame('missing_userid', $result->errorCode);
    }
}
