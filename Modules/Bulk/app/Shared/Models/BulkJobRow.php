<?php

namespace Modules\Bulk\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Bulk\Shared\Enums\BulkRowStatus;

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

    /**
     * @return array<string, mixed>
     */
    public function validationFields(): array
    {
        return [
            'userid' => $this->user_id,
            'phonenumber' => $this->phone_number,
            ...($this->additional_data ?? []),
        ];
    }
}
