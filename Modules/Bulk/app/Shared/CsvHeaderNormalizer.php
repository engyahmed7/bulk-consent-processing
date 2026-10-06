<?php

namespace Modules\Bulk\Shared;

class CsvHeaderNormalizer
{
    public static function normalize(string $header): string
    {
        return strtolower(preg_replace('/[\s\-_]/', '', trim($header)) ?? trim($header));
    }
}
