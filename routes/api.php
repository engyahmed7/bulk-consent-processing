<?php

use Illuminate\Support\Facades\Route;
use Modules\Bulk\Http\Controllers\BulkJobController;
use Modules\Bulk\Http\Middleware\EnsureApiKey;

Route::middleware(EnsureApiKey::class)->group(function (): void {
    Route::get('bulk-jobs/{bulkJob}', [BulkJobController::class, 'show']);
    Route::get('bulk-jobs/{bulkJob}/result', [BulkJobController::class, 'result']);
});
