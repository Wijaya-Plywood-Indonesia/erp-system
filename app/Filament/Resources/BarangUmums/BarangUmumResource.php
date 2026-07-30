<?php

namespace App\Filament\Resources\BarangUmums;

use App\Filament\Resources\BarangUmums\Pages\CreateBarangUmum;
use App\Filament\Resources\BarangUmums\Pages\EditBarangUmum;
use App\Filament\Resources\BarangUmums\Pages\ListBarangUmums;
use App\Filament\Resources\BarangUmums\Schemas\BarangUmumForm;
use App\Filament\Resources\BarangUmums\Tables\BarangUmumsTable;
use App\Models\BarangUmum;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BarangUmumResource extends Resource
{
    protected static ?string $model = BarangUmum::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return BarangUmumForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BarangUmumsTable::configure($table);
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
            'index' => ListBarangUmums::route('/'),
            'create' => CreateBarangUmum::route('/create'),
            'edit' => EditBarangUmum::route('/{record}/edit'),
        ];
    }
}
