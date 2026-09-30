<?php

namespace App\Domains\Bulk\Services\Csv;

use Generator;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BulkCsvStreamer
{
    /**
     * @return Generator<int, array{row_number: int, userid: string, phonenumber: string, additional_data: array<string, string>}>
     */
    public function rows(string $disk, string $path): Generator
    {
        $stream = Storage::disk($disk)->readStream($path);

        if ($stream === false) {
            throw new RuntimeException("CSV input file not found: {$path}");
        }

        try {
            $headerMap = null;
            $headerLabels = [];
            $rowNumber = 0;

            while (($values = fgetcsv($stream)) !== false) {
                $rowNumber++;

                if ($values === [null] || $values === false) {
                    continue;
                }

                $values = array_map(
                    static fn ($value): string => trim((string) $value),
                    $values,
                );

                if ($rowNumber === 1 && isset($values[0])) {
                    $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', $values[0]) ?? $values[0];
                }

                if ($headerMap === null) {
                    $headerMap = [];
                    $headerLabels = [];

                    foreach ($values as $columnIndex => $header) {
                        $normalizedHeader = strtolower(preg_replace('/[\s\-_]/', '', $header) ?? $header);

                        if ($normalizedHeader === '') {
                            continue;
                        }

                        if (array_key_exists($normalizedHeader, $headerMap)) {
                            throw new RuntimeException("Duplicate CSV header: {$header}");
                        }

                        $headerMap[$normalizedHeader] = $columnIndex;
                        $headerLabels[$normalizedHeader] = $header;
                    }

                    $this->assertRequiredHeaders(array_keys($headerMap));

                    continue;
                }

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $additionalData = [];

                foreach ($headerMap as $normalizedHeader => $columnIndex) {
                    if (in_array($normalizedHeader, ['userid', 'phonenumber'], true)) {
                        continue;
                    }

                    $additionalData[$headerLabels[$normalizedHeader]] = $values[$columnIndex] ?? '';
                }

                yield [
                    'row_number' => $rowNumber,
                    'userid' => $values[$headerMap['userid']] ?? '',
                    'phonenumber' => $values[$headerMap['phonenumber']] ?? '',
                    'additional_data' => $additionalData,
                ];
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @param  list<string>  $headers
     */
    private function assertRequiredHeaders(array $headers): void
    {
        foreach (config('bulk.required_headers', ['userid', 'phonenumber']) as $header) {
            if (! in_array($header, $headers, true)) {
                throw new RuntimeException("Missing required CSV header: {$header}");
            }
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }
}
