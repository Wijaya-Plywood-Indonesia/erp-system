<?php

namespace App\Filament\Resources\ValidasiRepairs;

use App\Filament\Resources\ValidasiRepairs\Pages\CreateValidasiRepair;
use App\Filament\Resources\ValidasiRepairs\Pages\EditValidasiRepair;
use App\Filament\Resources\ValidasiRepairs\Pages\ListValidasiRepairs;
use App\Filament\Resources\ValidasiRepairs\Schemas\ValidasiRepairForm;
use App\Filament\Resources\ValidasiRepairs\Tables\ValidasiRepairsTable;
use App\Models\ValidasiRepair;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiRepairResource extends Resource
{
    protected static ?string $model = ValidasiRepair::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public static function form(Schema $schema): Schema
    {
        return ValidasiRepairForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiRepairsTable::configure($table);
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
            'index' => ListValidasiRepairs::route('/'),
            'create' => CreateValidasiRepair::route('/create'),
            'edit' => EditValidasiRepair::route('/{record}/edit'),
        ];
    }
}