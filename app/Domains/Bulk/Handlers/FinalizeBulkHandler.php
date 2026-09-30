<?php

namespace App\Domains\Bulk\Handlers;

use App\Domains\Bulk\Enums\BulkJobStatus;
use App\Domains\Bulk\Models\BulkJob;
use App\Domains\Bulk\Services\Csv\BulkResultWriter;
use App\Domains\Bulk\Services\Storage\WormStorage;
use Throwable;

class FinalizeBulkHandler
{
    public function __construct(
        private BulkResultWriter $resultWriter,
        private WormStorage $wormStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $jobId = (int) ($payload['bulk_job_id'] ?? 0);
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
}
