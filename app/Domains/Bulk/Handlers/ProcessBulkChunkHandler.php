<?php

namespace App\Domains\Bulk\Handlers;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Bulk\Enums\BulkChunkStatus;
use App\Domains\Bulk\Enums\BulkRowStatus;
use App\Domains\Bulk\Models\BulkJob;
use App\Domains\Bulk\Models\BulkJobChunk;
use App\Domains\Bulk\Models\BulkJobRow;
use App\Domains\Bulk\Services\Consent\ConsentClientInterface;
use App\Domains\Bulk\Services\Validation\RowValidator;
use App\Infrastructure\RabbitMq\RabbitMqPublisher;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessBulkChunkHandler
{
    public function __construct(
        private RowValidator $rowValidator,
        private ConsentClientInterface $consentClient,
        private RabbitMqPublisher $publisher,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $chunkId = (int) ($payload['chunk_id'] ?? 0);
        $chunk = BulkJobChunk::query()->with('job')->findOrFail($chunkId);

        $claimed = BulkJobChunk::query()
            ->whereKey($chunk->id)
            ->where('status', BulkChunkStatus::Pending->value)
            ->update([
                'status' => BulkChunkStatus::Processing->value,
                'attempts' => DB::raw('attempts + 1'),
                'processing_started_at' => now(),
            ]);

        if ($claimed !== 1) {
            return;
        }

        $job = $chunk->job;

        try {
            $rows = BulkJobRow::query()
                ->where('chunk_id', $chunk->id)
                ->where('status', BulkRowStatus::Pending)
                ->orderBy('row_number')
                ->get();

            foreach ($rows as $row) {
                $validation = $this->rowValidator->validate($row->user_id, $row->phone_number);

                if (! $validation->valid) {
                    $row->update([
                        'user_id' => $validation->userId,
                        'phone_number' => $validation->phoneNumber,
                        'status' => BulkRowStatus::Failed,
                        'error_code' => $validation->errorCode,
                        'error_message' => $validation->errorMessage,
                    ]);

                    continue;
                }

                $consent = $this->consentClient->updateConsent(
                    $validation->userId,
                    $validation->phoneNumber,
                    $job->action,
                );

                if (! $consent->success) {
                    $row->update([
                        'user_id' => $validation->userId,
                        'phone_number' => $validation->phoneNumber,
                        'status' => BulkRowStatus::Failed,
                        'error_code' => $consent->errorCode,
                        'error_message' => $consent->errorMessage,
                    ]);

                    continue;
                }

                $row->update([
                    'user_id' => $validation->userId,
                    'phone_number' => $validation->phoneNumber,
                    'status' => BulkRowStatus::Success,
                    'error_code' => null,
                    'error_message' => null,
                ]);
            }

            $success = BulkJobRow::query()
                ->where('chunk_id', $chunk->id)
                ->where('status', BulkRowStatus::Success->value)
                ->count();
            $failed = BulkJobRow::query()
                ->where('chunk_id', $chunk->id)
                ->where('status', BulkRowStatus::Failed->value)
                ->count();

            DB::transaction(function () use ($chunk, $job, $success, $failed): void {
                BulkJobChunk::query()->whereKey($chunk->id)->update([
                    'status' => BulkChunkStatus::Completed,
                    'processing_started_at' => null,
                ]);

                BulkJob::query()->whereKey($job->id)->update([
                    'processed_rows' => DB::raw('processed_rows + '.($success + $failed)),
                    'success_rows' => DB::raw('success_rows + '.$success),
                    'failed_rows' => DB::raw('failed_rows + '.$failed),
                    'chunks_done' => DB::raw('chunks_done + 1'),
                ]);
            });
        } catch (Throwable $exception) {
            BulkJobChunk::query()
                ->whereKey($chunk->id)
                ->where('status', BulkChunkStatus::Processing->value)
                ->update([
                    'status' => BulkChunkStatus::Pending->value,
                    'processing_started_at' => null,
                ]);

            throw $exception;
        }

        $job->refresh();

        if ($job->chunks_done >= $job->chunks_total && $job->chunks_total > 0) {
            $this->publisher->publish(BrokerQueuePurpose::BulkFinalize, [
                'type' => 'finalize_bulk',
                'bulk_job_id' => $job->id,
            ]);
        }
    }
}
