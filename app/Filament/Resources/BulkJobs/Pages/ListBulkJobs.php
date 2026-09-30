<?php

namespace App\Filament\Resources\BulkJobs\Pages;

use App\Filament\Resources\BulkJobs\BulkJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBulkJobs extends ListRecords
{
    protected static string $resource = BulkJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
