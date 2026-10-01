<?php

namespace App\Filament\Resources\BulkJobs\Schemas;

use App\Domains\Bulk\Enums\BulkJobStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BulkJobInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job Information')
                    ->description('General information about this bulk operation.')
                    ->icon('heroicon-o-briefcase')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('uuid')
                            ->label('Process ID')
                            ->copyable()
                            ->copyMessage('Process ID copied')
                            ->copyMessageDuration(1500)
                            ->fontFamily('mono')
                            ->columnSpanFull(),

                        TextEntry::make('action')
                            ->label('Action')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn(BulkJobStatus $state): string => match ($state) {
                                BulkJobStatus::Completed => 'success',
                                BulkJobStatus::Failed => 'danger',
                                BulkJobStatus::Processing => 'warning',
                                BulkJobStatus::Partial => 'gray',
                                default => 'gray',
                            }),
                    ]),

                Section::make('Input File')
                    ->description('Source file used for this bulk operation.')
                    ->icon('heroicon-o-document')
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('original_filename')
                            ->label('CSV File')
                            ->placeholder('—')
                            ->weight('medium'),

                        TextEntry::make('input_path')
                            ->label('MinIO Path')
                            ->copyable()
                            ->copyMessage('Path copied')
                            ->copyMessageDuration(1500)
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ]),

                Section::make('Processing Summary')
                    ->description('Progress and results of the bulk operation.')
                    ->icon('heroicon-o-chart-bar')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('total_rows')
                            ->label('Total Rows')
                            ->numeric()
                            ->weight('bold'),

                        TextEntry::make('processed_rows')
                            ->label('Processed')
                            ->numeric()
                            ->weight('bold'),

                        TextEntry::make('success_rows')
                            ->label('Successful')
                            ->numeric()
                            ->weight('bold')
                            ->color('success'),

                        TextEntry::make('failed_rows')
                            ->label('Failed')
                            ->numeric()
                            ->weight('bold')
                            ->color('danger'),
                    ]),

                Section::make('Errors')
                    ->description('Details about failed rows, if any.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('error_summary')
                            ->label('Error Summary')
                            ->placeholder('No errors reported.')
                            ->prose()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record): bool => filled($record?->error_summary)),

                Section::make('Timestamps')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('M d, Y H:i:s'),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('M d, Y H:i:s'),
                    ]),
            ]);
    }
}
