<?php

namespace App\Filament\Resources\KendaraanSupplierKayus;

use App\Filament\Resources\KendaraanSupplierKayus\Pages\CreateKendaraanSupplierKayu;
use App\Filament\Resources\KendaraanSupplierKayus\Pages\EditKendaraanSupplierKayu;
use App\Filament\Resources\KendaraanSupplierKayus\Pages\ListKendaraanSupplierKayus;
use App\Filament\Resources\KendaraanSupplierKayus\Pages\ViewKendaraanSupplierKayu;
use App\Filament\Resources\KendaraanSupplierKayus\Schemas\KendaraanSupplierKayuForm;
use App\Filament\Resources\KendaraanSupplierKayus\Schemas\KendaraanSupplierKayuInfolist;
use App\Filament\Resources\KendaraanSupplierKayus\Tables\KendaraanSupplierKayusTable;
use App\Models\KendaraanSupplierKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class KendaraanSupplierKayuResource extends Resource
{
    protected static ?string $model = KendaraanSupplierKayu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Kayu';
    protected static ?string $recordTitleAttribute = 'nopol_kendaraan';

    public static function form(Schema $schema): Schema
    {
        return KendaraanSupplierKayuForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KendaraanSupplierKayuInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KendaraanSupplierKayusTable::configure($table);
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
            'index' => ListKendaraanSupplierKayus::route('/'),
            'create' => CreateKendaraanSupplierKayu::route('/create'),
            'view' => ViewKendaraanSupplierKayu::route('/{record}'),
            'edit' => EditKendaraanSupplierKayu::route('/{record}/edit'),
        ];
    }
}
