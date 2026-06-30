<?php

namespace App\Filament\Resources\BahanPenolongProduksis;

use App\Filament\Resources\BahanPenolongProduksis\Pages\CreateBahanPenolongProduksi;
use App\Filament\Resources\BahanPenolongProduksis\Pages\EditBahanPenolongProduksi;
use App\Filament\Resources\BahanPenolongProduksis\Pages\ListBahanPenolongProduksis;
use App\Filament\Resources\BahanPenolongProduksis\Schemas\BahanPenolongProduksiForm;
use App\Filament\Resources\BahanPenolongProduksis\Tables\BahanPenolongProduksisTable;
use App\Models\BahanPenolongProduksi;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BahanPenolongProduksiResource extends Resource
{
    protected static ?string $model = BahanPenolongProduksi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return BahanPenolongProduksiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BahanPenolongProduksisTable::configure($table);
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
            'index' => ListBahanPenolongProduksis::route('/'),
            'create' => CreateBahanPenolongProduksi::route('/create'),
            'edit' => EditBahanPenolongProduksi::route('/{record}/edit'),
        ];
    }
}
