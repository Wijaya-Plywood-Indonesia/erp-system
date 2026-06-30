<?php

namespace App\Filament\Resources\RekapKayuMasuks;

use App\Filament\Resources\RekapKayuMasuks\Pages\CreateRekapKayuMasuk;
use App\Filament\Resources\RekapKayuMasuks\Pages\EditRekapKayuMasuk;
use App\Filament\Resources\RekapKayuMasuks\Pages\ListRekapKayuMasuks;
use App\Filament\Resources\RekapKayuMasuks\Schemas\RekapKayuMasukForm;
use App\Filament\Resources\RekapKayuMasuks\Tables\RekapKayuMasuksTable;
use App\Models\RekapKayuMasuk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RekapKayuMasukResource extends Resource
{
    protected static ?string $model = RekapKayuMasuk::class;

    //Identitas Menu/Model/Feartue atau apalah itu/
    protected static ?string $modelLabel = 'Laporan Kayu Masuk';
    protected static ?string $pluralModelLabel = 'Laporan Kayu Masuk';
    protected static string|UnitEnum|null $navigationGroup = 'Laporan';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    //end

    public static function table(Table $table): Table
    {
        return RekapKayuMasuksTable::configure($table);
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
            'index' => ListRekapKayuMasuks::route('/'),
        ];
    }
}
