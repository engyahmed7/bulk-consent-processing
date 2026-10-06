<?php

namespace Modules\Bulk\Processing\Validation;

use Illuminate\Support\Facades\Validator;
use Modules\Bulk\Shared\CsvHeaderNormalizer;

class RowValidator
{
    public function __construct(
        private CsvFieldRuleRegistry $ruleRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public function validate(array $row): RowValidationResult
    {
        $fields = [];

        foreach ($row as $header => $value) {
            $fields[CsvHeaderNormalizer::normalize((string) $header)] = $value;
        }

        $rules = [];
        foreach ($this->ruleRegistry->all() as $field => $rule) {
            $rules[$field] = $rule->rules();

            if (array_key_exists($field, $fields)) {
                $fields[$field] = $rule->normalize($fields[$field]);
            }
        }

        $validator = Validator::make($fields, $rules);

        if ($validator->fails()) {
            $field = (string) array_key_first($validator->errors()->getMessages());
            $rule = $this->ruleRegistry->forField($field);

            return RowValidationResult::invalid(
                $fields,
                $field,
                $rule?->errorCode($fields[$field] ?? null) ?? 'invalid_data',
                $validator->errors()->first($field),
            );
        }

        return RowValidationResult::ok($fields);
    }
}
