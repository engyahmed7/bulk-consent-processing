<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Storage;
use Modules\Bulk\Parsing\BulkCsvStreamer;
use RuntimeException;
use Tests\TestCase;

class BulkCsvStreamerTest extends TestCase
{
    public function test_rows_yields_userid_phonenumber_and_additional_columns(): void
    {
        Storage::fake('minio');
        Storage::disk('minio')->put('inputs/sample.csv', "userid,phonenumber,email\nu1,966500000001,u1@example.test\n");

        $rows = iterator_to_array(app(BulkCsvStreamer::class)->rows('minio', 'inputs/sample.csv'));

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['row_number']);
        $this->assertSame('u1', $rows[0]['userid']);
        $this->assertSame('966500000001', $rows[0]['phonenumber']);
        $this->assertSame(['email' => 'u1@example.test'], $rows[0]['additional_data']);
    }

    public function test_rows_fails_when_required_headers_are_missing(): void
    {
        Storage::fake('minio');
        Storage::disk('minio')->put('inputs/sample.csv', "name,phone\nJane,966500000001\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required CSV header:');

        iterator_to_array(app(BulkCsvStreamer::class)->rows('minio', 'inputs/sample.csv'));
    }

    public function test_rows_fails_when_headers_duplicate_after_normalization(): void
    {
        Storage::fake('minio');
        Storage::disk('minio')->put(
            'inputs/sample.csv',
            "userid,user_id,phonenumber\nu1,u1,966500000001\n",
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate CSV header: user_id');

        iterator_to_array(app(BulkCsvStreamer::class)->rows('minio', 'inputs/sample.csv'));
    }
}
