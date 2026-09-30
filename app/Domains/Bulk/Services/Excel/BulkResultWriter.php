<?php

namespace App\Domains\Bulk\Services\Excel;

use App\Domains\Bulk\Models\BulkJob;
use App\Domains\Bulk\Models\BulkJobRow;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class BulkResultWriter
{
    public function writeToTempFile(BulkJob $job): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'bulk_result_');
        if ($tempPath === false) {
            throw new \RuntimeException('Unable to create temporary result file.');
        }

        $xlsxPath = $tempPath.'.xlsx';
        rename($tempPath, $xlsxPath);

        $firstRow = BulkJobRow::query()
            ->where('bulk_job_id', $job->id)
            ->orderBy('row_number')
            ->first();
        $additionalHeaders = array_keys($firstRow?->additional_data ?? []);

        $writer = new Writer;
        $writer->openToFile($xlsxPath);
        $writer->addRow(Row::fromValues([
            'userid',
            'phonenumber',
            'status',
            'error_code',
            'error_message',
            ...$additionalHeaders,
        ]));

        BulkJobRow::query()
            ->where('bulk_job_id', $job->id)
            ->orderBy('row_number')
            ->cursor()
            ->each(function (BulkJobRow $row) use ($writer, $additionalHeaders): void {
                $writer->addRow(Row::fromValues([
                    $row->user_id,
                    $row->phone_number,
                    $row->status->value,
                    $row->error_code,
                    $row->error_message,
                    ...array_map(static fn (string $header): string => $row->additional_data[$header] ?? '', $additionalHeaders),
                ]));
            });

        $writer->close();

        return $xlsxPath;
    }
}
