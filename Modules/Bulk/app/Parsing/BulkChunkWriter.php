<?php

namespace Modules\Bulk\Parsing;

use RuntimeException;

class BulkChunkWriter
{
    /**
     * @param  list<array{row_number: int, userid: string, phonenumber: string, additional_data: array<string, string>}>  $rows
     */
    public function writeToTempFile(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bulk_chunk_');

        if ($path === false) {
            throw new RuntimeException('Unable to create temporary CSV chunk file.');
        }

        $stream = fopen($path, 'wb');

        if ($stream === false) {
            @unlink($path);

            throw new RuntimeException('Unable to open temporary CSV chunk file.');
        }

        $additionalHeaders = array_keys($rows[0]['additional_data'] ?? []);

        try {
            $this->writeRow($stream, ['source_row', 'userid', 'phonenumber', ...$additionalHeaders]);

            foreach ($rows as $row) {
                $this->writeRow($stream, [
                    (string) $row['row_number'],
                    $row['userid'],
                    $row['phonenumber'],
                    ...array_map(static fn (string $header): string => $row['additional_data'][$header] ?? '', $additionalHeaders),
                ]);
            }
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
