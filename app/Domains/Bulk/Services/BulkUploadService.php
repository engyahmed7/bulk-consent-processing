<?php

namespace App\Domains\Bulk\Services;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Bulk\Enums\BulkJobStatus;
use App\Domains\Bulk\Enums\ConsentAction;
use App\Domains\Bulk\Models\BulkJob;
use App\Infrastructure\RabbitMq\RabbitMqPublisher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BulkUploadService
{
    public function __construct(
        private RabbitMqPublisher $publisher,
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

        $job = BulkJob::query()->create([
            'uuid' => $uuid,
            'action' => $action,
            'status' => BulkJobStatus::Queued,
            'original_filename' => $file->getClientOriginalName(),
            'input_path' => $storedPath,
            'created_by' => $createdBy,
        ]);

        DB::afterCommit(function () use ($job): void {
            $this->publisher->publish(BrokerQueuePurpose::BulkParse, [
                'type' => 'parse_bulk',
                'bulk_job_id' => $job->id,
            ]);
        });

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
