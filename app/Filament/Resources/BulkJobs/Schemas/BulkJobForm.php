<?php

namespace App\Filament\Resources\BulkJobs\Schemas;

use App\Domains\Bulk\Enums\ConsentAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class BulkJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('action')
                    ->label('Consent action')
                    ->options([
                        ConsentAction::OptIn->value => 'Opt in',
                        ConsentAction::OptOut->value => 'Opt out',
                    ])
                    ->required()
                    ->native(false),
                FileUpload::make('file')
                    ->label('CSV file')
                    ->disk('minio')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                    ])
                    ->rules([
                        'file',
                        'mimes:csv,txt',
                        'max:'.(int) config('bulk.max_upload_kb', 51200),
                    ])
                    ->maxSize((int) config('bulk.max_upload_kb', 51200))
                    ->required()
                    ->storeFiles(false)
                    ->helperText('Upload a CSV with userid and phonenumber columns. The file is stored in MinIO.'),
            ]);
    }
}
