<?php

namespace Tests\Feature;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Broker\Services\BrokerQueueSyncService;
use App\Domains\Bulk\Enums\BulkChunkStatus;
use App\Domains\Bulk\Enums\BulkJobStatus;
use App\Domains\Bulk\Enums\BulkRowStatus;
use App\Domains\Bulk\Enums\ConsentAction;
use App\Domains\Bulk\Handlers\FinalizeBulkHandler;
use App\Domains\Bulk\Handlers\ParseBulkCsvHandler;
use App\Domains\Bulk\Handlers\ProcessBulkChunkHandler;
use App\Domains\Bulk\Models\BulkJob;
use App\Domains\Bulk\Models\BulkJobChunk;
use App\Domains\Bulk\Models\BulkJobRow;
use App\Domains\Bulk\Services\BulkUploadService;
use App\Domains\Bulk\Services\Consent\ConsentClientInterface;
use App\Domains\Bulk\Services\Consent\ConsentResult;
use App\Domains\Bulk\Services\Storage\WormArchive;
use App\Domains\Bulk\Services\Storage\WormStorage;
use App\Infrastructure\RabbitMq\RabbitMqConsumer;
use App\Infrastructure\RabbitMq\RabbitMqPublisher;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('minio');
        Storage::disk('minio')->makeDirectory('inputs');

        $this->mock(RabbitMqPublisher::class, function ($mock): void {
            $mock->shouldReceive('publish')->andReturnNull();
        });
    }

    public function test_broker_queue_can_be_registered_via_api(): void
    {
        $response = $this->withHeader('X-API-Key', 'test-api-key')
            ->postJson('/api/broker-queues', [
                'purpose' => BrokerQueuePurpose::BulkParse->value,
                'queue_name' => 'ops.bulk.parse.v1',
                'routing_key' => 'ops.bulk.parse.v1',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.queue_name', 'ops.bulk.parse.v1');

        $this->assertDatabaseHas('broker_queues', [
            'purpose' => 'bulk_parse',
            'queue_name' => 'ops.bulk.parse.v1',
            'is_active' => true,
        ]);
    }

    public function test_consumer_startup_does_not_sync_and_mutate_broker_queue_mappings(): void
    {
        $this->mock(BrokerQueueSyncService::class, function ($mock): void {
            $mock->shouldReceive('sync')->never();
        });
        $this->mock(RabbitMqConsumer::class, function ($mock): void {
            $mock->shouldReceive('consume')
                ->once()
                ->withArgs(static fn (BrokerQueuePurpose $purpose, Closure $handler): bool => $purpose === BrokerQueuePurpose::BulkValidate);
        });

        $this->artisan('bulk:consume', ['purpose' => 'bulk_validate'])
            ->assertExitCode(0);
    }

    public function test_bulk_job_upload_is_not_available_on_the_api(): void
    {
        $this->withHeader('X-API-Key', 'test-api-key')
            ->post('/api/bulk-jobs', [
                'action' => ConsentAction::OptIn->value,
            ])
            ->assertNotFound();
    }

    public function test_upload_service_stores_csv_on_minio_and_returns_the_process_id(): void
    {
        $job = app(BulkUploadService::class)->upload(
            $this->makeCsvUpload([['u1', '966500000001']]),
            ConsentAction::OptIn,
            'ops@example.test',
        );

        $this->assertSame(BulkJobStatus::Queued, $job->status);
        $this->assertNotSame('', $job->uuid);
        $this->assertSame('ops@example.test', $job->created_by);
        $this->assertTrue(Storage::disk('minio')->exists($job->input_path));
        $this->assertStringStartsWith('inputs/'.$job->uuid.'/', $job->input_path);
        $this->assertStringEndsWith('.csv', $job->input_path);
        $this->assertStringNotContainsString(storage_path('app'), $job->input_path);
    }

    public function test_parse_handler_creates_chunks_and_rows(): void
    {
        config(['bulk.chunk_size' => 2]);
        $archivedPaths = [];
        $archivedRows = [];
        $this->mock(WormStorage::class, function ($mock) use (&$archivedPaths, &$archivedRows): void {
            $mock->shouldReceive('putFileOnce')
                ->twice()
                ->andReturnUsing(function (string $path, string $tempPath) use (&$archivedPaths, &$archivedRows): string {
                    $this->assertFileExists($tempPath);
                    $this->assertGreaterThan(0, filesize($tempPath));
                    $archivedPaths[] = $path;
                    $archivedRows[] = $this->readCsvRows($tempPath);

                    return $path;
                });
        });

        $path = $this->storeCsvFixture([
            ['u1', '966500000001', 'u1@example.test', 'gold'],
            ['u2', '966500000002', 'u2@example.test', 'silver'],
            ['u3', '966500000003', 'u3@example.test', 'bronze'],
        ], ['userid', 'phonenumber', 'email', 'payment plan']);

        $job = BulkJob::query()->create([
            'action' => ConsentAction::OptIn,
            'status' => BulkJobStatus::Queued,
            'original_filename' => 'fixture.csv',
            'input_path' => $path,
        ]);

        app(ParseBulkCsvHandler::class)->handle([
            'bulk_job_id' => $job->id,
        ]);

        $job->refresh();

        $this->assertSame(3, $job->total_rows);
        $this->assertSame(2, $job->chunks_total);
        $this->assertSame(BulkJobStatus::Processing, $job->status);
        $this->assertSame(3, BulkJobRow::query()->count());
        $this->assertSame($archivedPaths, BulkJobChunk::query()->orderBy('chunk_index')->pluck('worm_path')->all());
        $this->assertSame(['email' => 'u1@example.test', 'payment plan' => 'gold'], BulkJobRow::query()->where('row_number', 2)->firstOrFail()->additional_data);
        $this->assertStringEndsWith('.csv', $archivedPaths[0]);
        $this->assertSame(['source_row', 'userid', 'phonenumber', 'email', 'payment plan'], $archivedRows[0][0]);
        $this->assertSame(['2', 'u1', '966500000001', 'u1@example.test', 'gold'], $archivedRows[0][1]);
    }

    public function test_chunk_handler_marks_invalid_phone_without_consent_call(): void
    {
        $consent = $this->mock(ConsentClientInterface::class);
        $consent->shouldReceive('updateConsent')->never();

        $job = BulkJob::query()->create([
            'action' => ConsentAction::OptIn,
            'status' => BulkJobStatus::Processing,
            'original_filename' => 'fixture.csv',
            'input_path' => 'inputs/x.csv',
            'total_rows' => 1,
            'chunks_total' => 1,
        ]);

        $chunk = BulkJobChunk::query()->create([
            'bulk_job_id' => $job->id,
            'chunk_index' => 0,
            'status' => BulkChunkStatus::Pending,
            'row_from' => 2,
            'row_to' => 2,
        ]);

        BulkJobRow::query()->create([
            'bulk_job_id' => $job->id,
            'chunk_id' => $chunk->id,
            'row_number' => 2,
            'user_id' => 'u1',
            'phone_number' => 'bad',
            'status' => BulkRowStatus::Pending,
        ]);

        app(ProcessBulkChunkHandler::class)->handle([
            'bulk_job_id' => $job->id,
            'chunk_id' => $chunk->id,
        ]);

        $row = BulkJobRow::query()->first();
        $this->assertSame(BulkRowStatus::Failed, $row->status);
        $this->assertSame('invalid_phonenumber', $row->error_code);
    }

    public function test_chunk_handler_calls_consent_for_valid_rows(): void
    {
        $consent = $this->mock(ConsentClientInterface::class);
        $consent->shouldReceive('updateConsent')
            ->once()
            ->andReturn(ConsentResult::ok());

        $job = BulkJob::query()->create([
            'action' => ConsentAction::OptOut,
            'status' => BulkJobStatus::Processing,
            'original_filename' => 'fixture.csv',
            'input_path' => 'inputs/x.csv',
            'total_rows' => 1,
            'chunks_total' => 1,
        ]);

        $chunk = BulkJobChunk::query()->create([
            'bulk_job_id' => $job->id,
            'chunk_index' => 0,
            'status' => BulkChunkStatus::Pending,
            'row_from' => 2,
            'row_to' => 2,
        ]);

        BulkJobRow::query()->create([
            'bulk_job_id' => $job->id,
            'chunk_id' => $chunk->id,
            'row_number' => 2,
            'user_id' => 'u1',
            'phone_number' => '966500000001',
            'status' => BulkRowStatus::Pending,
        ]);

        app(ProcessBulkChunkHandler::class)->handle([
            'bulk_job_id' => $job->id,
            'chunk_id' => $chunk->id,
        ]);

        $this->assertSame(BulkRowStatus::Success, BulkJobRow::query()->first()->status);
        $job->refresh();
        $this->assertSame(1, $job->success_rows);
        $this->assertSame(1, $job->chunks_done);
    }

    public function test_chunk_handler_does_not_claim_a_chunk_already_processing(): void
    {
        $consent = $this->mock(ConsentClientInterface::class);
        $consent->shouldReceive('updateConsent')->never();

        $job = BulkJob::query()->create([
            'action' => ConsentAction::OptIn,
            'status' => BulkJobStatus::Processing,
            'original_filename' => 'fixture.csv',
            'input_path' => 'inputs/x.csv',
            'total_rows' => 1,
            'chunks_total' => 1,
        ]);

        $chunk = BulkJobChunk::query()->create([
            'bulk_job_id' => $job->id,
            'chunk_index' => 0,
            'status' => BulkChunkStatus::Processing,
            'row_from' => 2,
            'row_to' => 2,
            'attempts' => 1,
        ]);

        BulkJobRow::query()->create([
            'bulk_job_id' => $job->id,
            'chunk_id' => $chunk->id,
            'row_number' => 2,
            'user_id' => 'u1',
            'phone_number' => '966500000001',
            'status' => BulkRowStatus::Pending,
        ]);

        app(ProcessBulkChunkHandler::class)->handle(['chunk_id' => $chunk->id]);

        $this->assertSame(1, $chunk->fresh()->attempts);
        $this->assertSame(BulkRowStatus::Pending, BulkJobRow::query()->firstOrFail()->status);
        $this->assertSame(0, $job->fresh()->chunks_done);
    }

    public function test_finalize_writes_result_to_worm_once(): void
    {
        $written = [];

        $this->mock(WormArchive::class, function ($mock) use (&$written): void {
            $mock->shouldReceive('writeFileOnce')
                ->once()
                ->andReturnUsing(function (string $key, string $tempPath) use (&$written): string {
                    $resultRows = $this->readCsvRows($tempPath);
                    $this->assertSame(['userid', 'phonenumber', 'status', 'error_code', 'error_message', 'email', 'payment plan'], $resultRows[0]);
                    $this->assertSame(['u1', '966500000001', 'success', '', '', 'u1@example.test', 'gold'], $resultRows[1]);

                    $written[$key] = true;

                    return $key;
                });

            $mock->shouldReceive('writeOnce')
                ->once()
                ->andReturnUsing(function (string $key) use (&$written): string {
                    if (isset($written[$key])) {
                        throw new \RuntimeException("WORM object already exists and cannot be overwritten: {$key}");
                    }

                    $written[$key] = true;

                    return $key;
                });
        });

        $job = BulkJob::query()->create([
            'action' => ConsentAction::OptIn,
            'status' => BulkJobStatus::Processing,
            'original_filename' => 'fixture.csv',
            'input_path' => 'inputs/x.csv',
            'total_rows' => 2,
            'processed_rows' => 2,
            'success_rows' => 1,
            'failed_rows' => 1,
            'chunks_total' => 1,
            'chunks_done' => 1,
        ]);

        BulkJobRow::query()->create([
            'bulk_job_id' => $job->id,
            'row_number' => 2,
            'user_id' => 'u1',
            'phone_number' => '966500000001',
            'additional_data' => ['email' => 'u1@example.test', 'payment plan' => 'gold'],
            'status' => BulkRowStatus::Success,
        ]);

        BulkJobRow::query()->create([
            'bulk_job_id' => $job->id,
            'row_number' => 3,
            'user_id' => 'u2',
            'phone_number' => 'bad',
            'additional_data' => ['email' => 'u2@example.test', 'payment plan' => 'silver'],
            'status' => BulkRowStatus::Failed,
            'error_code' => 'invalid_phonenumber',
            'error_message' => 'phonenumber format is invalid.',
        ]);

        app(FinalizeBulkHandler::class)->handle(['bulk_job_id' => $job->id]);

        $job->refresh();
        $this->assertSame(BulkJobStatus::Partial, $job->status);
        $this->assertNotNull($job->worm_result_path);
        $this->assertArrayHasKey($job->worm_result_path, $written);

        $this->expectException(\RuntimeException::class);
        app(WormStorage::class)->putOnce($job->worm_result_path, 'nope');
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     */
    private function makeCsvUpload(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bulk_csv_').'.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, ['userid', 'phonenumber']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return new UploadedFile($path, 'users.csv', 'text/csv', null, true);
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  list<string>  $headers
     */
    private function storeCsvFixture(array $rows, array $headers = ['userid', 'phonenumber']): string
    {
        $relative = 'inputs/fixture-'.uniqid('', true).'.csv';
        Storage::disk('minio')->put($relative, $this->csvContents($rows, $headers));

        return $relative;
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  list<string>  $headers
     */
    private function csvContents(array $rows, array $headers): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents === false ? '' : $contents;
    }

    /**
     * @return list<list<string>>
     */
    private function readCsvRows(string $path): array
    {
        $stream = fopen($path, 'rb');
        $rows = [];

        try {
            while (($row = fgetcsv($stream)) !== false) {
                $rows[] = array_map(static fn ($value): string => trim((string) $value), $row);
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }
}
