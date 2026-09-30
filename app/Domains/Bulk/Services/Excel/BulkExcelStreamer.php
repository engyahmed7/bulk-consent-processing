<?php

namespace App\Domains\Bulk\Services\Excel;

use Generator;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

class BulkExcelStreamer
{
    /**
     * @return Generator<int, array{row_number: int, userid: string, phonenumber: string, additional_data: array<string, string>}>
     */
    public function rows(string $disk, string $path): Generator
    {
        $absolutePath = Storage::disk($disk)->path($path);

        if (! is_file($absolutePath)) {
            throw new RuntimeException("Excel input file not found: {$absolutePath}");
        }

        $reader = new Reader;
        $reader->open($absolutePath);

        try {
            $headerMap = null;
            $headerLabels = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $values = array_map(
                        static fn ($value) => trim((string) $value),
                        $row->toArray(),
                    );

                    if ($index === 1) {
                        $headerMap = [];
                        $headerLabels = [];

                        foreach ($values as $columnIndex => $header) {
                            $normalizedHeader = strtolower(preg_replace('/[\s\-_]/', '', $header) ?? $header);

                            if ($normalizedHeader === '') {
                                continue;
                            }

                            if (array_key_exists($normalizedHeader, $headerMap)) {
                                throw new RuntimeException("Duplicate Excel header: {$header}");
                            }

                            $headerMap[$normalizedHeader] = $columnIndex;
                            $headerLabels[$normalizedHeader] = $header;
                        }

                        $this->assertRequiredHeaders(array_keys($headerMap));

                        continue;
                    }

                    if (! is_array($headerMap)) {
                        throw new RuntimeException('Excel file is missing a header row.');
                    }

                    $additionalData = [];

                    foreach ($headerMap as $normalizedHeader => $columnIndex) {
                        if (in_array($normalizedHeader, ['userid', 'phonenumber'], true)) {
                            continue;
                        }

                        $additionalData[$headerLabels[$normalizedHeader]] = $values[$columnIndex] ?? '';
                    }

                    yield [
                        'row_number' => $index,
                        'userid' => $values[$headerMap['userid']] ?? '',
                        'phonenumber' => $values[$headerMap['phonenumber']] ?? '',
                        'additional_data' => $additionalData,
                    ];
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  list<string>  $headers
     */
    private function assertRequiredHeaders(array $headers): void
    {
        foreach (config('bulk.required_headers', ['userid', 'phonenumber']) as $header) {
            if (! in_array($header, $headers, true)) {
                throw new RuntimeException("Missing required Excel header: {$header}");
            }
        }
    }
}
