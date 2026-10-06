<?php

namespace Modules\Bulk\Console;

use Illuminate\Console\Command;
use Modules\Bulk\Shared\Storage\WormArchive;
use Throwable;

class EnsureWormBucketCommand extends Command
{
    protected $signature = 'bulk:ensure-worm-bucket';

    protected $description = 'Create the MinIO worm-archive bucket with Object Lock (like s3-worm-store)';

    public function handle(WormArchive $archive): int
    {
        if (! $archive->isReachable()) {
            $endpoint = (string) config('filesystems.disks.minio.endpoint');
            $this->error("MinIO is not reachable at {$endpoint}. Start it with: docker compose up -d minio");

            return self::FAILURE;
        }

        try {
            $archive->ensureReady();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $bucket = (string) config('filesystems.disks.minio.bucket');
        $mode = (string) config('worm.lock_mode');
        $this->info("WORM bucket ready: {$bucket} (Object Lock {$mode})");

        return self::SUCCESS;
    }
}
