<?php

use App\Domains\Bulk\Http\Controllers\BulkJobController;
use App\Domains\Bulk\Http\Middleware\EnsureApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureApiKey::class)->group(function (): void {
    Route::get('bulk-jobs/{bulkJob}', [BulkJobController::class, 'show']);
    Route::get('bulk-jobs/{bulkJob}/result', [BulkJobController::class, 'result']);
});
