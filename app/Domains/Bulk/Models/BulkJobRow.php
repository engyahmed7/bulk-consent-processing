<?php

namespace App\Domains\Bulk\Models;

use App\Domains\Bulk\Enums\BulkRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkJobRow extends Model
{
    protected $fillable = [
        'bulk_job_id',
        'chunk_id',
        'row_number',
        'user_id',
        'phone_number',
        'additional_data',
        'status',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => BulkRowStatus::class,
            'row_number' => 'integer',
            'additional_data' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(BulkJob::class, 'bulk_job_id');
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(BulkJobChunk::class, 'chunk_id');
    }
}
