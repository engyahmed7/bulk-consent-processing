<?php

namespace App\Filament\Resources\BulkJobs;

use App\Filament\Resources\BulkJobs\Pages\CreateBulkJob;
use App\Filament\Resources\BulkJobs\Pages\ListBulkJobs;
use App\Filament\Resources\BulkJobs\Pages\ViewBulkJob;
use App\Filament\Resources\BulkJobs\Schemas\BulkJobForm;
use App\Filament\Resources\BulkJobs\Schemas\BulkJobInfolist;
use App\Filament\Resources\BulkJobs\Tables\BulkJobsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Bulk\Shared\Models\BulkJob;
use UnitEnum;

class BulkJobResource extends Resource
{
    protected static ?string $model = BulkJob::class;

    protected static ?string $recordTitleAttribute = 'uuid';

    protected static ?string $navigationLabel = 'Bulk Jobs';

    protected static string|UnitEnum|null $navigationGroup = 'Bulk Management';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return BulkJobForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BulkJobInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BulkJobsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBulkJobs::route('/'),
            'create' => CreateBulkJob::route('/create'),
            'view' => ViewBulkJob::route('/{record}'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }
}
