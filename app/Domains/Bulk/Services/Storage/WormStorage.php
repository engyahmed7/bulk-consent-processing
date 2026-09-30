<?php

namespace App\Domains\Bulk\Services\Storage;

/**
 * Thin facade used by FinalizeBulkHandler / BulkJobController.
 * Delegates to WormArchive (same pattern as s3-worm-store).
 */
class WormStorage
{
    public function __construct(
        private WormArchive $archive,
    ) {}

    public function putOnce(string $path, string $contents): string
    {
        return $this->archive->writeOnce(
            $path,
            $contents,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
    }

    public function putFileOnce(string $path, string $localAbsolutePath): string
    {
        return $this->archive->writeFileOnce(
            $path,
            $localAbsolutePath,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
    }

    public function readStream(string $path)
    {
        return $this->archive->readStream($path);
    }
}
