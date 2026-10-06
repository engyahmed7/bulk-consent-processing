<?php

namespace Modules\Bulk\Processing\Validation;

use Illuminate\Support\Facades\File;
use LogicException;
use Modules\Bulk\Shared\CsvHeaderNormalizer;
use ReflectionClass;

class CsvFieldRuleRegistry
{
    /**
     * @var array<string, CsvFieldRule>
     */
    private array $rules;

    public function __construct()
    {
        $this->rules = [];
        $directory = __DIR__ . '/Rules';
        $namespace = __NAMESPACE__ . '\\Rules\\';

        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($directory) + 1);
            $class = $namespace . str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $relativePath,
            );

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || ! $reflection->implementsInterface(CsvFieldRule::class)) {
                continue;
            }

            $rule = app($class);
            $field = CsvHeaderNormalizer::normalize($rule->field());

            if ($field === '' || isset($this->rules[$field])) {
                throw new LogicException("CSV field rule is missing a unique field name: {$class}");
            }

            $this->rules[$field] = $rule;
        }
    }

    /**
     * @return array<string, CsvFieldRule>
     */
    public function all(): array
    {
        return $this->rules;
    }

    public function forField(string $field): ?CsvFieldRule
    {
        return $this->rules[CsvHeaderNormalizer::normalize($field)] ?? null;
    }

    /**
     * @return list<string>
     */
    public function requiredFields(): array
    {
        return array_keys(array_filter(
            $this->rules,
            static fn(CsvFieldRule $rule): bool => $rule->required(),
        ));
    }
}
