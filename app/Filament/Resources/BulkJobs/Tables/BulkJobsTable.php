<?php

namespace App\Filament\Resources\BulkJobs\Tables;

use App\Domains\Bulk\Models\BulkJob;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BulkJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')
                    ->label('Process ID')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('original_filename')
                    ->label('CSV file')
                    ->searchable(),
                TextColumn::make('processed_rows')
                    ->label('Progress')
                    ->formatStateUsing(fn (mixed $state, BulkJob $record): string => $record->progressPercent().'%'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
