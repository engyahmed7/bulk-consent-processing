<?php

namespace App\Domains\Bulk\Services;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Bulk\Enums\BulkJobStatus;
use App\Domains\Bulk\Enums\ConsentAction;
use App\Domains\Bulk\Models\BulkJob;
use App\Infrastructure\RabbitMq\RabbitMqPublisher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkUploadService
{
    public function __construct(
        private RabbitMqPublisher $publisher,
    ) {}

    public function upload(UploadedFile $file, ConsentAction $action, ?string $createdBy = null): BulkJob
    {
        $uuid = (string) Str::uuid();
        $disk = (string) config('bulk.input_disk', 'bulk');
        $path = "inputs/{$uuid}/".$file->getClientOriginalName();

        Storage::disk($disk)->putFileAs(
            "inputs/{$uuid}",
            $file,
            $file->getClientOriginalName(),
        );

        $job = BulkJob::query()->create([
            'uuid' => $uuid,
            'action' => $action,
            'status' => BulkJobStatus::Queued,
            'original_filename' => $file->getClientOriginalName(),
            'input_path' => $path,
            'created_by' => $createdBy,
        ]);

        $this->publisher->publish(BrokerQueuePurpose::BulkParse, [
            'type' => 'parse_bulk',
            'bulk_job_id' => $job->id,
        ]);

        return $job;
    }
}
