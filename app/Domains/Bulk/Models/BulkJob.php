<?php

namespace App\Domains\Bulk\Models;

use App\Domains\Bulk\Enums\BulkJobStatus;
use App\Domains\Bulk\Enums\ConsentAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BulkJob extends Model
{
    protected $fillable = [
        'uuid',
        'action',
        'status',
        'original_filename',
        'input_path',
        'worm_result_path',
        'total_rows',
        'processed_rows',
        'success_rows',
        'failed_rows',
        'chunks_total',
        'chunks_done',
        'error_summary',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'action' => ConsentAction::class,
            'status' => BulkJobStatus::class,
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'success_rows' => 'integer',
            'failed_rows' => 'integer',
            'chunks_total' => 'integer',
            'chunks_done' => 'integer',
        ];
    }

    protected static function booting(): void
    {
        static::creating(function (BulkJob $job): void {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(BulkJobChunk::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(BulkJobRow::class);
    }

    public function progressPercent(): int
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return (int) min(100, floor(($this->processed_rows / $this->total_rows) * 100));
    }
}
