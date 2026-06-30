<?php

namespace App\Filament\Resources\HasilRepairs;

use App\Filament\Resources\HasilRepairs\Pages\CreateHasilRepair;
use App\Filament\Resources\HasilRepairs\Pages\EditHasilRepair;
use App\Filament\Resources\HasilRepairs\Pages\ListHasilRepairs;
use App\Filament\Resources\HasilRepairs\Schemas\HasilRepairForm;
use App\Filament\Resources\HasilRepairs\Tables\HasilRepairsTable;
use App\Models\HasilRepair;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HasilRepairResource extends Resource
{
    protected static ?string $model = HasilRepair::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public static function form(Schema $schema): Schema
    {
        return HasilRepairForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HasilRepairsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHasilRepairs::route('/'),
            'create' => CreateHasilRepair::route('/create'),
            'edit' => EditHasilRepair::route('/{record}/edit'),
        ];
    }
}
