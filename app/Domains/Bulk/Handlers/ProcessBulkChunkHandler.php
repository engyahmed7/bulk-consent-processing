<?php

namespace App\Domains\Bulk\Handlers;

use App\Domains\Bulk\Enums\BulkChunkStatus;
use App\Domains\Bulk\Enums\BulkJobStatus;
use App\Domains\Bulk\Enums\BulkRowStatus;
use App\Domains\Bulk\Messages\FinalizeBulkJobRequested;
use App\Domains\Bulk\Models\BulkJob;
use App\Domains\Bulk\Models\BulkJobChunk;
use App\Domains\Bulk\Models\BulkJobRow;
use App\Domains\Bulk\Services\Consent\ConsentClientInterface;
use App\Domains\Bulk\Services\Validation\RowValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Features\RabbitMQ\Contracts\MessageHandler;
use Modules\Core\Features\RabbitMQ\Messages\Envelope;
use Modules\Core\Features\RabbitMQ\Publishing\Outbox;
use Throwable;

class ProcessBulkChunkHandler implements MessageHandler
{
    public function __construct(
        private RowValidator $rowValidator,
        private ConsentClientInterface $consentClient,
        private Outbox $outbox,
    ) {}

    public function handle(Envelope $envelope): void
    {
        $chunkId = (int) ($envelope->payload['chunk_id'] ?? 0);
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
                $lockedJob = BulkJob::query()->lockForUpdate()->findOrFail($job->id);
                $completed = BulkJobChunk::query()->whereKey($chunk->id)
                    ->where('status', BulkChunkStatus::Processing)
                    ->update([
                        'status' => BulkChunkStatus::Completed,
                        'processing_started_at' => null,
                    ]);

                if ($completed !== 1) {
                    return;
                }

                $lockedJob->processed_rows += $success + $failed;
                $lockedJob->success_rows += $success;
                $lockedJob->failed_rows += $failed;
                $lockedJob->chunks_done++;
                $lockedJob->save();

                if ($lockedJob->chunks_done >= $lockedJob->chunks_total && $lockedJob->chunks_total > 0) {
                    $this->outbox->record(new FinalizeBulkJobRequested($lockedJob->id));
                }
            });

            Log::info('Bulk CSV validation chunk completed', [
                'bulk_job_id' => $job->id,
                'bulk_job_uuid' => $job->uuid,
                'chunk_id' => $chunk->id,
                'chunk_index' => $chunk->chunk_index,
                'rows_processed' => $success + $failed,
                'success_rows' => $success,
                'failed_rows' => $failed,
            ]);
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

    }

    public function failed(Envelope $envelope, Throwable $exception): void
    {
        $chunkId = (int) ($envelope->payload['chunk_id'] ?? 0);
        $chunk = BulkJobChunk::query()->find($chunkId);

        if (! $chunk instanceof BulkJobChunk) {
            return;
        }

        DB::transaction(function () use ($chunk, $exception): void {
            BulkJobChunk::query()->whereKey($chunk->id)->update([
                'status' => BulkChunkStatus::Failed,
                'processing_started_at' => null,
            ]);
            BulkJob::query()->whereKey($chunk->bulk_job_id)->update([
                'status' => BulkJobStatus::Failed,
                'error_summary' => $exception->getMessage(),
            ]);
        });
    }
}
