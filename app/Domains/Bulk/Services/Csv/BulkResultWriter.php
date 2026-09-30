<?php

namespace App\Domains\Bulk\Services\Csv;

use App\Domains\Bulk\Models\BulkJob;
use App\Domains\Bulk\Models\BulkJobRow;
use RuntimeException;

class BulkResultWriter
{
    public function writeToTempFile(BulkJob $job): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bulk_result_');

        if ($path === false) {
            throw new RuntimeException('Unable to create temporary CSV result file.');
        }

        $stream = fopen($path, 'wb');

        if ($stream === false) {
            @unlink($path);

            throw new RuntimeException('Unable to open temporary CSV result file.');
        }

        $firstRow = BulkJobRow::query()
            ->where('bulk_job_id', $job->id)
            ->orderBy('row_number')
            ->first();
        $additionalHeaders = array_keys($firstRow?->additional_data ?? []);

        try {
            $this->writeRow($stream, [
                'userid',
                'phonenumber',
                'status',
                'error_code',
                'error_message',
                ...$additionalHeaders,
            ]);

            BulkJobRow::query()
                ->where('bulk_job_id', $job->id)
                ->orderBy('row_number')
                ->cursor()
                ->each(function (BulkJobRow $row) use ($stream, $additionalHeaders): void {
                    $this->writeRow($stream, [
                        $row->user_id,
                        $row->phone_number,
                        $row->status->value,
                        $row->error_code ?? '',
                        $row->error_message ?? '',
                        ...array_map(static fn (string $header): string => $row->additional_data[$header] ?? '', $additionalHeaders),
                    ]);
                });
        } finally {
            fclose($stream);
        }

        return $path;
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $values
     */
    private function writeRow($stream, array $values): void
    {
        if (fputcsv($stream, $values, ',', '"', '\\', "\r\n") === false) {
            throw new RuntimeException('Unable to write CSV row.');
        }
    }
}
