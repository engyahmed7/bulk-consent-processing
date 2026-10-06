<?php

namespace Modules\Bulk\Parsing;

use Modules\Bulk\Processing\Validation\CsvFieldRuleRegistry;
use Modules\Bulk\Shared\CsvHeaderNormalizer;
use RuntimeException;

class CsvSchemaValidator
{
    public function __construct(
        private CsvFieldRuleRegistry $ruleRegistry,
    ) {}

    /**
     * @param  list<string>  $headers
     * @return array{indexes: array<string, int>, labels: array<string, string>}
     */
    public function validate(array $headers): array
    {
        $indexes = [];
        $labels = [];

        foreach ($headers as $columnIndex => $header) {
            $field = CsvHeaderNormalizer::normalize($header);

            if ($field === '') {
                continue;
            }

            if (array_key_exists($field, $indexes)) {
                throw new RuntimeException("Duplicate CSV header: {$header}");
            }

            $indexes[$field] = $columnIndex;
            $labels[$field] = $header;
        }

        foreach ($this->ruleRegistry->requiredFields() as $requiredField) {
            if (! array_key_exists($requiredField, $indexes)) {
                throw new RuntimeException("Missing required CSV header: {$requiredField}");
            }
        }

        return [
            'indexes' => $indexes,
            'labels' => $labels,
        ];
    }
}
