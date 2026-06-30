<?php

namespace App\Filament\Resources\RencanaRepairs;

use App\Filament\Resources\RencanaRepairs\Pages\CreateRencanaRepair;
use App\Filament\Resources\RencanaRepairs\Pages\EditRencanaRepair;
use App\Filament\Resources\RencanaRepairs\Pages\ListRencanaRepairs;
use App\Filament\Resources\RencanaRepairs\Schemas\RencanaRepairForm;
use App\Filament\Resources\RencanaRepairs\Tables\RencanaRepairsTable;
use App\Models\RencanaRepair;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RencanaRepairResource extends Resource
{
    protected static ?string $model = RencanaRepair::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return RencanaRepairForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RencanaRepairsTable::configure($table);
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
            'index' => ListRencanaRepairs::route('/'),
            'create' => CreateRencanaRepair::route('/create'),
            'edit' => EditRencanaRepair::route('/{record}/edit'),
        ];
    }
}
