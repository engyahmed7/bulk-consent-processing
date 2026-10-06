<?php

namespace Modules\Bulk\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Bulk\Shared\Enums\BulkChunkStatus;

class BulkJobChunk extends Model
{
    protected $fillable = [
        'bulk_job_id',
        'chunk_index',
        'status',
        'row_from',
        'row_to',
        'worm_path',
        'attempts',
        'processing_started_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BulkChunkStatus::class,
            'chunk_index' => 'integer',
            'row_from' => 'integer',
            'row_to' => 'integer',
            'attempts' => 'integer',
            'processing_started_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(BulkJob::class, 'bulk_job_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(BulkJobRow::class, 'chunk_id');
    }
}
