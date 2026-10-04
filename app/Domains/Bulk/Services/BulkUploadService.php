<?php

namespace App\Domains\Bulk\Services;

use App\Domains\Bulk\Enums\BulkJobStatus;
use App\Domains\Bulk\Enums\ConsentAction;
use App\Domains\Bulk\Messages\ParseBulkCsvRequested;
use App\Domains\Bulk\Models\BulkJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Features\RabbitMQ\Publishing\Outbox;

class BulkUploadService
{
    public function __construct(
        private Outbox $outbox,
    ) {}

    public function upload(UploadedFile $file, ConsentAction $action, ?string $createdBy = null): BulkJob
    {
        $uuid = (string) Str::uuid();
        $disk = (string) config('bulk.input_disk', 'minio');
        $filename = $this->safeCsvFilename($file);
        $directory = "inputs/{$uuid}";
        $storedPath = $file->storeAs($directory, $filename, ['disk' => $disk]);

        if (! is_string($storedPath) || $storedPath === '') {
            throw new \RuntimeException('Unable to store the uploaded CSV file.');
        }

        $job = DB::transaction(function () use ($uuid, $action, $file, $storedPath, $createdBy): BulkJob {
            $job = BulkJob::query()->create([
                'uuid' => $uuid,
                'action' => $action,
                'status' => BulkJobStatus::Queued,
                'original_filename' => $file->getClientOriginalName(),
                'input_path' => $storedPath,
                'created_by' => $createdBy,
            ]);

            $this->outbox->record(new ParseBulkCsvRequested($job->id));

            return $job;
        });

        Log::info('Bulk CSV upload queued for parsing', [
            'bulk_job_id' => $job->id,
            'bulk_job_uuid' => $job->uuid,
            'action' => $job->action->value,
        ]);

        return $job;
    }

    private function safeCsvFilename(UploadedFile $file): string
    {
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($basename);

        if ($slug === '') {
            $slug = 'upload';
        }

        return $slug.'.csv';
    }
}
