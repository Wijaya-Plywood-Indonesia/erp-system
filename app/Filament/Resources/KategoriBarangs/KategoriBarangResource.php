<?php

namespace App\Filament\Resources\KategoriBarangs;

use App\Filament\Resources\KategoriBarangs\Pages\CreateKategoriBarang;
use App\Filament\Resources\KategoriBarangs\Pages\EditKategoriBarang;
use App\Filament\Resources\KategoriBarangs\Pages\ListKategoriBarangs;
use App\Filament\Resources\KategoriBarangs\Schemas\KategoriBarangForm;
use App\Filament\Resources\KategoriBarangs\Tables\KategoriBarangsTable;
use App\Models\KategoriBarang;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KategoriBarangResource extends Resource
{
    protected static ?string $model = KategoriBarang::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'KategoriBarang';

    public static function form(Schema $schema): Schema
    {
        return KategoriBarangForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KategoriBarangsTable::configure($table);
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
            'index' => ListKategoriBarangs::route('/'),
            'create' => CreateKategoriBarang::route('/create'),
            'edit' => EditKategoriBarang::route('/{record}/edit'),
        ];
    }
}
