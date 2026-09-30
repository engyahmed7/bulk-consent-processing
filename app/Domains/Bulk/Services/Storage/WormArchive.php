<?php

namespace App\Domains\Bulk\Services\Storage;

use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use RuntimeException;
use Throwable;

/**
 * WORM archive aligned with https://github.com/engyahmed7/s3-worm-store WormArchive.
 */
class WormArchive
{
    private S3Client $client;

    private string $bucket;

    private string $lockMode;

    private int $retentionDays;

    public function __construct()
    {
        $disk = config('filesystems.disks.minio', []);

        $this->bucket = (string) ($disk['bucket'] ?? 'worm-archive');
        $this->lockMode = strtoupper((string) config('worm.lock_mode', 'GOVERNANCE'));
        $this->retentionDays = max(1, (int) config('worm.retention_days', 365));

        $this->client = new S3Client([
            'version' => 'latest',
            'region' => (string) ($disk['region'] ?? 'us-east-1'),
            'endpoint' => $disk['endpoint'] ?? 'http://127.0.0.1:9002',
            'use_path_style_endpoint' => (bool) ($disk['use_path_style_endpoint'] ?? true),
            'credentials' => [
                'key' => (string) ($disk['key'] ?? ''),
                'secret' => (string) ($disk['secret'] ?? ''),
            ],
        ]);
    }

    public function isReachable(): bool
    {
        try {
            $this->client->listBuckets();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function writeOnce(string $key, string $contents, string $contentType = 'application/octet-stream'): string
    {
        $this->ensureReady();

        if ($this->objectExists($key)) {
            throw new RuntimeException("WORM object already exists and cannot be overwritten: {$key}");
        }

        $retainUntil = now()->addDays($this->retentionDays)->utc();

        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $contents,
            'ContentType' => $contentType,
            'ObjectLockMode' => $this->lockMode,
            'ObjectLockRetainUntilDate' => $retainUntil,
        ]);

        return $key;
    }

    public function writeFileOnce(string $key, string $localAbsolutePath, string $contentType = 'application/octet-stream'): string
    {
        $contents = file_get_contents($localAbsolutePath);
        if ($contents === false) {
            throw new RuntimeException("Unable to read local file for WORM upload: {$localAbsolutePath}");
        }

        return $this->writeOnce($key, $contents, $contentType);
    }

    /**
     * @return resource
     */
    public function readStream(string $key)
    {
        $this->ensureReady();

        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
        } catch (S3Exception $exception) {
            if ($this->isMissing($exception)) {
                throw new RuntimeException("WORM object not found: {$key}");
            }

            throw $exception;
        }

        $body = $result['Body'] ?? null;
        if (is_resource($body)) {
            return $body;
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Unable to open temp stream for WORM object.');
        }

        fwrite($stream, (string) $body);
        rewind($stream);

        return $stream;
    }

    public function ensureReady(): void
    {
        if ($this->client->doesBucketExist($this->bucket)) {
            return;
        }

        $this->client->createBucket([
            'Bucket' => $this->bucket,
            'ObjectLockEnabledForBucket' => true,
        ]);

        try {
            $this->client->putObjectLockConfiguration([
                'Bucket' => $this->bucket,
                'ObjectLockConfiguration' => [
                    'ObjectLockEnabled' => 'Enabled',
                    'Rule' => [
                        'DefaultRetention' => [
                            'Mode' => $this->lockMode,
                            'Days' => $this->retentionDays,
                        ],
                    ],
                ],
            ]);
        } catch (AwsException $exception) {
            // Bucket created with lock; default retention config may already exist.
        }
    }

    private function objectExists(string $key): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return true;
        } catch (S3Exception $exception) {
            if ($this->isMissing($exception)) {
                return false;
            }

            throw $exception;
        }
    }

    private function isMissing(S3Exception $exception): bool
    {
        return $exception->getStatusCode() === 404
            || in_array($exception->getAwsErrorCode(), ['NotFound', 'NoSuchKey', 'NoSuchBucket'], true);
    }
}
