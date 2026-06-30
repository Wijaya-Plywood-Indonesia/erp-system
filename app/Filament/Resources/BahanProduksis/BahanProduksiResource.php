<?php

namespace App\Filament\Resources\BahanProduksis;

use App\Filament\Resources\BahanProduksis\Pages\CreateBahanProduksi;
use App\Filament\Resources\BahanProduksis\Pages\EditBahanProduksi;
use App\Filament\Resources\BahanProduksis\Pages\ListBahanProduksis;
use App\Filament\Resources\BahanProduksis\Schemas\BahanProduksiForm;
use App\Filament\Resources\BahanProduksis\Tables\BahanProduksisTable;
use App\Models\BahanProduksi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BahanProduksiResource extends Resource
{
    protected static ?string $model = BahanProduksi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return BahanProduksiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BahanProduksisTable::configure($table);
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
            'index' => ListBahanProduksis::route('/'),
            'create' => CreateBahanProduksi::route('/create'),
            'edit' => EditBahanProduksi::route('/{record}/edit'),
        ];
    }
}
