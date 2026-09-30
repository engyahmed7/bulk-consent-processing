<?php

namespace App\Filament\Resources\BulkJobs\Pages;

use App\Domains\Bulk\Enums\ConsentAction;
use App\Domains\Bulk\Services\BulkUploadService;
use App\Filament\Resources\BulkJobs\BulkJobResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class CreateBulkJob extends CreateRecord
{
    protected static string $resource = BulkJobResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $file = $this->resolveUploadedFile($data['file'] ?? null);

        return app(BulkUploadService::class)->upload(
            $file,
            ConsentAction::from((string) $data['action']),
            Auth::user()?->email,
        );
    }

    protected function getCreatedNotification(): ?Notification
    {
        $processId = $this->getRecord()?->uuid;

        return Notification::make()
            ->success()
            ->title('Bulk job queued')
            ->body($processId === null ? null : 'Process ID: '.$processId);
    }

    private function resolveUploadedFile(mixed $file): UploadedFile
    {
        if (is_array($file)) {
            $file = Arr::first($file);
        }

        if ($file instanceof TemporaryUploadedFile) {
            return $file;
        }

        if ($file instanceof UploadedFile) {
            return $file;
        }

        throw new RuntimeException('A CSV file is required.');
    }
}
