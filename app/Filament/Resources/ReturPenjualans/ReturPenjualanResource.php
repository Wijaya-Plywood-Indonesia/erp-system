<?php

namespace App\Filament\Resources\ReturPenjualans;

use App\Filament\Resources\ReturPenjualans\Pages\CreateReturPenjualan;
use App\Filament\Resources\ReturPenjualans\Pages\EditReturPenjualan;
use App\Filament\Resources\ReturPenjualans\Pages\FormReturPenjualan;
use App\Filament\Resources\ReturPenjualans\Pages\ListReturPenjualans;
use App\Filament\Resources\ReturPenjualans\Schemas\ReturPenjualanForm;
use App\Filament\Resources\ReturPenjualans\Tables\ReturPenjualansTable;
use App\Models\ReturPenjualan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReturPenjualanResource extends Resource
{
    protected static ?string $model = ReturPenjualan::class;

    protected static ?string $modelLabel = 'Retur Penjualan';

    protected static ?string $pluralModelLabel = 'Retur Penjualan';

    protected static ?string $navigationLabel = 'Retur Penjualan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'BK-BM';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return ReturPenjualanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReturPenjualansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'       => ListReturPenjualans::route('/'),
            'form-retur'  => FormReturPenjualan::route('/form'),
            'create'      => CreateReturPenjualan::route('/create'),
            'edit'        => EditReturPenjualan::route('/{record}/edit'),
        ];
    }
}
