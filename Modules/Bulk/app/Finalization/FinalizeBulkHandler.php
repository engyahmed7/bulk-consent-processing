<?php

namespace Modules\Bulk\Finalization;

use Illuminate\Support\Facades\Log;
use Modules\Bulk\Shared\Enums\BulkJobStatus;
use Modules\Bulk\Shared\Models\BulkJob;
use Modules\Bulk\Shared\Storage\WormStorage;
use Modules\Core\Features\RabbitMQ\Contracts\MessageHandler;
use Modules\Core\Features\RabbitMQ\Messages\Envelope;
use Throwable;

class FinalizeBulkHandler implements MessageHandler
{
    public function __construct(
        private BulkResultWriter $resultWriter,
        private WormStorage $wormStorage,
    ) {}

    public function handle(Envelope $envelope): void
    {
        $jobId = (int) ($envelope->payload['bulk_job_id'] ?? 0);
        $job = BulkJob::query()->findOrFail($jobId);

        if (in_array($job->status, [BulkJobStatus::Completed, BulkJobStatus::Partial], true) && filled($job->worm_result_path)) {
            return;
        }

        $job->update(['status' => BulkJobStatus::Finalizing]);

        $tempPath = null;

        try {
            $tempPath = $this->resultWriter->writeToTempFile($job);
            $objectKey = sprintf('bulk-results/%s/result-%s.csv', $job->uuid, now()->format('YmdHis'));
            $this->wormStorage->putFileOnce($objectKey, $tempPath);

            $status = match (true) {
                $job->failed_rows === 0 && $job->success_rows > 0 => BulkJobStatus::Completed,
                $job->success_rows === 0 => BulkJobStatus::Failed,
                default => BulkJobStatus::Partial,
            };

            $job->update([
                'worm_result_path' => $objectKey,
                'status' => $status,
            ]);

            Log::info('Bulk CSV job finalized', [
                'bulk_job_id' => $job->id,
                'bulk_job_uuid' => $job->uuid,
                'status' => $status->value,
                'total_rows' => $job->total_rows,
                'success_rows' => $job->success_rows,
                'failed_rows' => $job->failed_rows,
                'result_path' => $objectKey,
            ]);
        } catch (Throwable $exception) {
            $job->update([
                'status' => BulkJobStatus::Failed,
                'error_summary' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            if (is_string($tempPath) && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function failed(Envelope $envelope, Throwable $exception): void
    {
        BulkJob::query()->whereKey((int) ($envelope->payload['bulk_job_id'] ?? 0))->update([
            'status' => BulkJobStatus::Failed,
            'error_summary' => $exception->getMessage(),
        ]);
    }
}
