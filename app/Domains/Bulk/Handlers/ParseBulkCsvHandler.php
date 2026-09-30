<?php

namespace App\Domains\Bulk\Handlers;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Bulk\Enums\BulkChunkStatus;
use App\Domains\Bulk\Enums\BulkJobStatus;
use App\Domains\Bulk\Enums\BulkRowStatus;
use App\Domains\Bulk\Models\BulkJob;
use App\Domains\Bulk\Models\BulkJobChunk;
use App\Domains\Bulk\Models\BulkJobRow;
use App\Domains\Bulk\Services\Csv\BulkChunkWriter;
use App\Domains\Bulk\Services\Csv\BulkCsvStreamer;
use App\Domains\Bulk\Services\Storage\WormStorage;
use App\Infrastructure\RabbitMq\RabbitMqPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ParseBulkCsvHandler
{
    public function __construct(
        private BulkCsvStreamer $streamer,
        private BulkChunkWriter $chunkWriter,
        private WormStorage $wormStorage,
        private RabbitMqPublisher $publisher,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $jobId = (int) ($payload['bulk_job_id'] ?? 0);
        $job = BulkJob::query()->findOrFail($jobId);

        if (in_array($job->status, [BulkJobStatus::Processing, BulkJobStatus::Completed, BulkJobStatus::Partial, BulkJobStatus::Finalizing], true)) {
            return;
        }

        $job->update(['status' => BulkJobStatus::Parsing]);

        Log::info('Bulk CSV parsing started', [
            'bulk_job_id' => $job->id,
            'bulk_job_uuid' => $job->uuid,
        ]);

        $chunkSize = (int) config('bulk.chunk_size', 1000);
        $disk = (string) config('bulk.input_disk', 'minio');

        $buffer = [];
        $rowCount = 0;
        $chunkIndex = 0;
        $chunkStart = 2;

        try {
            foreach ($this->streamer->rows($disk, $job->input_path) as $row) {
                $rowCount++;
                $buffer[] = $row;

                if (count($buffer) >= $chunkSize) {
                    $this->flushChunk($job, $chunkIndex, $chunkStart, $buffer);
                    $chunkIndex++;
                    $chunkStart = $row['row_number'] + 1;
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                $this->flushChunk($job, $chunkIndex, $chunkStart, $buffer);
                $chunkIndex++;
            }

            $job->update([
                'total_rows' => $rowCount,
                'chunks_total' => $chunkIndex,
                'status' => $rowCount === 0 ? BulkJobStatus::Failed : BulkJobStatus::Processing,
                'error_summary' => $rowCount === 0 ? 'CSV contained no data rows.' : null,
            ]);

            if ($rowCount === 0) {
                Log::warning('Bulk CSV contained no data rows', [
                    'bulk_job_id' => $job->id,
                    'bulk_job_uuid' => $job->uuid,
                ]);

                return;
            }

            Log::info('Bulk CSV parsing completed', [
                'bulk_job_id' => $job->id,
                'bulk_job_uuid' => $job->uuid,
                'total_rows' => $rowCount,
                'chunks_total' => $chunkIndex,
            ]);

            $job->chunks()->orderBy('chunk_index')->each(function (BulkJobChunk $chunk) use ($job): void {
                $this->publisher->publish(BrokerQueuePurpose::BulkValidate, [
                    'type' => 'process_chunk',
                    'bulk_job_id' => $job->id,
                    'chunk_id' => $chunk->id,
                ]);
            });
        } catch (Throwable $exception) {
            $job->update([
                'status' => BulkJobStatus::Failed,
                'error_summary' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  list<array{row_number: int, userid: string, phonenumber: string, additional_data: array<string, string>}>  $rows
     */
    private function flushChunk(BulkJob $job, int $chunkIndex, int $rowFrom, array $rows): void
    {
        $rowTo = $rows[array_key_last($rows)]['row_number'];
        $tempPath = $this->chunkWriter->writeToTempFile($rows);

        try {
            $objectKey = sprintf(
                'bulk-chunks/%s/chunk-%04d-%s.csv',
                $job->uuid,
                $chunkIndex,
                (string) Str::uuid(),
            );
            $wormPath = $this->wormStorage->putFileOnce($objectKey, $tempPath);

            DB::transaction(function () use ($job, $chunkIndex, $rowFrom, $rowTo, $rows, $wormPath): void {
                $chunk = BulkJobChunk::query()->create([
                    'bulk_job_id' => $job->id,
                    'chunk_index' => $chunkIndex,
                    'status' => BulkChunkStatus::Pending,
                    'row_from' => $rowFrom,
                    'row_to' => $rowTo,
                    'worm_path' => $wormPath,
                ]);

                $now = now();
                $inserts = array_map(static fn (array $row): array => [
                    'bulk_job_id' => $job->id,
                    'chunk_id' => $chunk->id,
                    'row_number' => $row['row_number'],
                    'user_id' => $row['userid'],
                    'phone_number' => $row['phonenumber'],
                    'additional_data' => json_encode($row['additional_data'], JSON_THROW_ON_ERROR),
                    'status' => BulkRowStatus::Pending->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $rows);

                BulkJobRow::query()->insert($inserts);
            });
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}
