<?php

namespace App\Filament\Resources\BulkJobs\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Bulk\Shared\Enums\ConsentAction;

class BulkJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bulk upload')
                    ->description('Choose the consent action and upload the source CSV file.')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        Select::make('action')
                            ->label('Consent action')
                            ->options([
                                ConsentAction::OptIn->value => 'Opt in',
                                ConsentAction::OptOut->value => 'Opt out',
                            ])
                            ->placeholder('Select an action')
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->helperText('Whether this update should opt the user in or out of consent.'),

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
                            ->directory('uploads')
                            ->preserveFilenames()
                            ->previewable(false)
                            ->openable(false)
                            ->helperText('Upload a CSV containing the required userid and phonenumber columns. The file is stored in MinIO before processing.')
                            ->hintIcon('heroicon-o-information-circle', 'CSV files are processed asynchronously in chunks after upload.'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
