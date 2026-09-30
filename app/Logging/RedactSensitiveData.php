<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RedactSensitiveData implements ProcessorInterface
{
    public function __construct(
        private readonly array $keys
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->shouldRedact($key)) {
                $data[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    private function shouldRedact(string|int $key): bool
    {
        return in_array(
            strtolower((string) $key),
            array_map('strtolower', $this->keys),
            true
        );
    }
}
