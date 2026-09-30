<?php

namespace App\Domains\Broker\Http\Controllers;

use App\Domains\Broker\Http\Requests\StoreBrokerQueueRequest;
use App\Domains\Broker\Http\Requests\UpdateBrokerQueueRequest;
use App\Domains\Broker\Models\BrokerQueue;
use App\Domains\Broker\Services\BrokerQueueSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

class BrokerQueueController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => BrokerQueue::query()->orderBy('purpose')->get(),
        ]);
    }

    public function sync(BrokerQueueSyncService $syncService): JsonResponse
    {
        try {
            $result = $syncService->sync();
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => 'Queues synced from RabbitMQ management API.',
            'data' => $result,
        ]);
    }

    public function store(StoreBrokerQueueRequest $request): JsonResponse
    {
        $queue = BrokerQueue::query()->create($request->validated());

        return response()->json(['data' => $queue], 201);
    }

    public function update(UpdateBrokerQueueRequest $request, BrokerQueue $brokerQueue): JsonResponse
    {
        $brokerQueue->update($request->validated());

        return response()->json(['data' => $brokerQueue->fresh()]);
    }

    public function destroy(BrokerQueue $brokerQueue): JsonResponse
    {
        $brokerQueue->update(['is_active' => false]);

        return response()->json(['data' => $brokerQueue->fresh()]);
    }
}
