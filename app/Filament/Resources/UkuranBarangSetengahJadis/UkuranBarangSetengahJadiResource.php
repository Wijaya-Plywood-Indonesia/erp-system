<?php

namespace App\Filament\Resources\UkuranBarangSetengahJadis;

use App\Filament\Resources\UkuranBarangSetengahJadis\Pages\CreateUkuranBarangSetengahJadi;
use App\Filament\Resources\UkuranBarangSetengahJadis\Pages\EditUkuranBarangSetengahJadi;
use App\Filament\Resources\UkuranBarangSetengahJadis\Pages\ListUkuranBarangSetengahJadis;
use App\Filament\Resources\UkuranBarangSetengahJadis\Schemas\UkuranBarangSetengahJadiForm;
use App\Filament\Resources\UkuranBarangSetengahJadis\Tables\UkuranBarangSetengahJadisTable;
use App\Models\BarangSetengahJadiHp;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UkuranBarangSetengahJadiResource extends Resource
{
    protected static ?string $model = BarangSetengahJadiHp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'BarangSetengahJadiHp';

    protected static ?string $modelLabel = 'Barang Setengah Jadi HP';
    protected static ?string $pluralModelLabel = 'Barang Setengah Jadi HP';

    public static function form(Schema $schema): Schema
    {
        return UkuranBarangSetengahJadiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UkuranBarangSetengahJadisTable::configure($table);
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
            'index' => ListUkuranBarangSetengahJadis::route('/'),
            'create' => CreateUkuranBarangSetengahJadi::route('/create'),
            'edit' => EditUkuranBarangSetengahJadi::route('/{record}/edit'),
        ];
    }
}
