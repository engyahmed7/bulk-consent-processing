<?php

use App\Domains\Broker\Http\Controllers\BrokerQueueController;
use App\Domains\Bulk\Http\Controllers\BulkJobController;
use App\Domains\Bulk\Http\Middleware\EnsureApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureApiKey::class)->group(function (): void {
    Route::get('broker-queues', [BrokerQueueController::class, 'index']);
    Route::post('broker-queues/sync', [BrokerQueueController::class, 'sync']);
    Route::post('broker-queues', [BrokerQueueController::class, 'store']);
    Route::put('broker-queues/{brokerQueue}', [BrokerQueueController::class, 'update']);
    Route::delete('broker-queues/{brokerQueue}', [BrokerQueueController::class, 'destroy']);

    Route::get('bulk-jobs/{bulkJob}', [BulkJobController::class, 'show']);
    Route::get('bulk-jobs/{bulkJob}/result', [BulkJobController::class, 'result']);
});
