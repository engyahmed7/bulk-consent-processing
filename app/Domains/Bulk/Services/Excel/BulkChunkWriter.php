<?php

namespace App\Domains\Bulk\Services\Excel;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class BulkChunkWriter
{
    /**
     * @param  list<array{row_number: int, userid: string, phonenumber: string, additional_data: array<string, string>}>  $rows
     */
    public function writeToTempFile(array $rows): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'bulk_chunk_');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to create temporary chunk file.');
        }

        $xlsxPath = $tempPath.'.xlsx';
        if (! rename($tempPath, $xlsxPath)) {
            @unlink($tempPath);

            throw new RuntimeException('Unable to prepare temporary chunk file.');
        }

        $writer = new Writer;
        $additionalHeaders = array_keys($rows[0]['additional_data'] ?? []);

        try {
            $writer->openToFile($xlsxPath);
            $writer->addRow(Row::fromValues([
                'source_row',
                'userid',
                'phonenumber',
                ...$additionalHeaders,
            ]));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues([
                    $row['row_number'],
                    $row['userid'],
                    $row['phonenumber'],
                    ...array_map(static fn (string $header): string => $row['additional_data'][$header] ?? '', $additionalHeaders),
                ]));
            }
        } finally {
            $writer->close();
        }

        return $xlsxPath;
    }
}
