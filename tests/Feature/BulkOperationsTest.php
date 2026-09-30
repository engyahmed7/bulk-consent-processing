<?php

namespace Tests\Feature;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Broker\Services\BrokerQueueSyncService;
use App\Domains\Bulk\Enums\BulkChunkStatus;
use App\Domains\Bulk\Enums\BulkJobStatus;
use App\Domains\Bulk\Enums\BulkRowStatus;
use App\Domains\Bulk\Enums\ConsentAction;
use App\Domains\Bulk\Handlers\FinalizeBulkHandler;
use App\Domains\Bulk\Handlers\ParseBulkExcelHandler;
use App\Domains\Bulk\Handlers\ProcessBulkChunkHandler;
use App\Domains\Bulk\Models\BulkJob;
use App\Domains\Bulk\Models\BulkJobChunk;
use App\Domains\Bulk\Models\BulkJobRow;
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
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class BulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::disk('bulk')->makeDirectory('inputs');

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

    public function test_upload_rejects_unauthorized_requests(): void
    {
        $this->postJson('/api/bulk-jobs', [])
            ->assertUnauthorized();
    }

    public function test_upload_accepts_xlsx_and_creates_job(): void
    {
        $file = $this->makeExcelUpload([
            ['u1', '966500000001'],
            ['u2', 'bad-phone'],
        ]);

        $response = $this->withHeader('X-API-Key', 'test-api-key')
            ->post('/api/bulk-jobs', [
                'action' => ConsentAction::OptIn->value,
                'file' => $file,
            ]);

        $response->assertAccepted()
            ->assertJsonStructure(['job_id', 'status']);

        $this->assertDatabaseHas('bulk_jobs', [
            'status' => BulkJobStatus::Queued->value,
            'action' => ConsentAction::OptIn->value,
        ]);
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
                    $archivedRows[] = $this->readExcelRows($tempPath);

                    return $path;
                });
        });

        $path = $this->storeExcelFixture([
            ['u1', '966500000001', 'u1@example.test', 'gold'],
            ['u2', '966500000002', 'u2@example.test', 'silver'],
            ['u3', '966500000003', 'u3@example.test', 'bronze'],
        ], ['userid', 'phonenumber', 'email', 'payment plan']);

        $job = BulkJob::query()->create([
            'action' => ConsentAction::OptIn,
            'status' => BulkJobStatus::Queued,
            'original_filename' => 'fixture.xlsx',
            'input_path' => $path,
        ]);

        app(ParseBulkExcelHandler::class)->handle([
            'bulk_job_id' => $job->id,
        ]);

        $job->refresh();

        $this->assertSame(3, $job->total_rows);
        $this->assertSame(2, $job->chunks_total);
        $this->assertSame(BulkJobStatus::Processing, $job->status);
        $this->assertSame(3, BulkJobRow::query()->count());
        $this->assertSame($archivedPaths, BulkJobChunk::query()->orderBy('chunk_index')->pluck('worm_path')->all());
        $this->assertSame(['email' => 'u1@example.test', 'payment plan' => 'gold'], BulkJobRow::query()->where('row_number', 2)->firstOrFail()->additional_data);
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
            'original_filename' => 'fixture.xlsx',
            'input_path' => 'inputs/x.xlsx',
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
            'original_filename' => 'fixture.xlsx',
            'input_path' => 'inputs/x.xlsx',
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
            'original_filename' => 'fixture.xlsx',
            'input_path' => 'inputs/x.xlsx',
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
                    $resultRows = $this->readExcelRows($tempPath);
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
            'original_filename' => 'fixture.xlsx',
            'input_path' => 'inputs/x.xlsx',
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
    private function makeExcelUpload(array $rows): UploadedFile
    {
        $path = $this->writeExcel($rows);

        return new UploadedFile($path, 'users.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     */
    private function storeExcelFixture(array $rows, array $headers = ['userid', 'phonenumber']): string
    {
        $absolute = $this->writeExcel($rows, $headers);
        $relative = 'inputs/fixture-'.uniqid('', true).'.xlsx';
        Storage::disk('bulk')->put($relative, file_get_contents($absolute));
        @unlink($absolute);

        return $relative;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function writeExcel(array $rows, array $headers = ['userid', 'phonenumber']): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bulk_xlsx_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    /**
     * @return list<list<string>>
     */
    private function readExcelRows(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(static fn ($value): string => trim((string) $value), $row->toArray());
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }
}
