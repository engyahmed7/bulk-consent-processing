<?php

namespace App\Domains\Bulk\Http\Controllers;

use App\Domains\Bulk\Enums\ConsentAction;
use App\Domains\Bulk\Http\Requests\StoreBulkJobRequest;
use App\Domains\Bulk\Models\BulkJob;
use App\Domains\Bulk\Services\BulkUploadService;
use App\Domains\Bulk\Services\Storage\WormStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkJobController extends Controller
{
    public function store(StoreBulkJobRequest $request, BulkUploadService $uploadService): JsonResponse
    {
        $action = ConsentAction::from($request->string('action')->toString());
        $job = $uploadService->upload($request->file('file'), $action);

        return response()->json([
            'job_id' => $job->uuid,
            'status' => $job->status->value,
        ], 202);
    }

    public function show(BulkJob $bulkJob): JsonResponse
    {
        return response()->json([
            'job_id' => $bulkJob->uuid,
            'status' => $bulkJob->status->value,
            'action' => $bulkJob->action->value,
            'original_filename' => $bulkJob->original_filename,
            'total_rows' => $bulkJob->total_rows,
            'processed_rows' => $bulkJob->processed_rows,
            'success_rows' => $bulkJob->success_rows,
            'failed_rows' => $bulkJob->failed_rows,
            'progress_percent' => $bulkJob->progressPercent(),
            'result_available' => filled($bulkJob->worm_result_path),
            'error_summary' => $bulkJob->error_summary,
        ]);
    }

    public function result(BulkJob $bulkJob, WormStorage $wormStorage): StreamedResponse|JsonResponse
    {
        if (! filled($bulkJob->worm_result_path)) {
            return response()->json(['message' => 'Result is not ready yet.'], 404);
        }

        $stream = $wormStorage->readStream($bulkJob->worm_result_path);

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 'bulk-result-'.$bulkJob->uuid.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
