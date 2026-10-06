<?php

namespace Modules\Bulk\Parsing;

use Generator;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BulkCsvStreamer
{
    public function __construct(
        private CsvSchemaValidator $schemaValidator,
    ) {}

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
            $schema = null;
            $rowNumber = 0;

            while (($values = fgetcsv($stream)) !== false) {
                $rowNumber++;

                if ($values === [null] || $values === false) {
                    continue;
                }

                $values = array_map(
                    static fn($value): string => trim((string) $value),
                    $values,
                );

                if ($rowNumber === 1 && isset($values[0])) {
                    $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', $values[0]) ?? $values[0];
                }

                if ($schema === null) {
                    $schema = $this->schemaValidator->validate($values);
                    continue;
                }

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $additionalData = [];

                foreach ($schema['indexes'] as $normalizedHeader => $columnIndex) {
                    if (in_array($normalizedHeader, ['userid', 'phonenumber'], true)) {
                        continue;
                    }

                    $additionalData[$schema['labels'][$normalizedHeader]] = $values[$columnIndex] ?? '';
                }

                yield [
                    'row_number' => $rowNumber,
                    'userid' => $values[$schema['indexes']['userid']] ?? '',
                    'phonenumber' => $values[$schema['indexes']['phonenumber']] ?? '',
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
