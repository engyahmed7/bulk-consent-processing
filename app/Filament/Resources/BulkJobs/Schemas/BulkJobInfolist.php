<?php

namespace App\Filament\Resources\BulkJobs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BulkJobInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('uuid')
                    ->label('Process ID')
                    ->copyable(),
                TextEntry::make('action')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('original_filename')
                    ->label('CSV file'),
                TextEntry::make('input_path')
                    ->label('MinIO path')
                    ->copyable(),
                TextEntry::make('total_rows'),
                TextEntry::make('processed_rows'),
                TextEntry::make('success_rows'),
                TextEntry::make('failed_rows'),
                TextEntry::make('error_summary')
                    ->placeholder('—'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
