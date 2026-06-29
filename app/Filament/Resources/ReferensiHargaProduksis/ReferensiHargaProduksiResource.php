<?php

namespace App\Filament\Resources\ReferensiHargaProduksis;

use App\Filament\Resources\ReferensiHargaProduksis\Pages\CreateReferensiHargaProduksi;
use App\Filament\Resources\ReferensiHargaProduksis\Pages\EditReferensiHargaProduksi;
use App\Filament\Resources\ReferensiHargaProduksis\Pages\ListReferensiHargaProduksis;
use App\Filament\Resources\ReferensiHargaProduksis\Schemas\ReferensiHargaProduksiForm;
use App\Filament\Resources\ReferensiHargaProduksis\Tables\ReferensiHargaProduksisTable;
use App\Models\ReferensiHargaProduksi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReferensiHargaProduksiResource extends Resource
{
    protected static ?string $model = ReferensiHargaProduksi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';

    protected static ?string $navigationLabel = 'Referensi Harga Produksi';

    protected static ?string $pluralModelLabel = 'Referensi Harga Produksi';

    protected static ?string $modelLabel = 'Referensi Harga Produksi';

    public static function form(Schema $schema): Schema
    {
        return ReferensiHargaProduksiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferensiHargaProduksisTable::configure($table);
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
            'index' => ListReferensiHargaProduksis::route('/'),
            'create' => CreateReferensiHargaProduksi::route('/create'),
            'edit' => EditReferensiHargaProduksi::route('/{record}/edit'),
        ];
    }
}
