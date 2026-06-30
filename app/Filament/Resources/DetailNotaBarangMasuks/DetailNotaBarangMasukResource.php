<?php

namespace App\Filament\Resources\DetailNotaBarangMasuks;

use App\Filament\Resources\DetailNotaBarangMasuks\Pages\CreateDetailNotaBarangMasuk;
use App\Filament\Resources\DetailNotaBarangMasuks\Pages\EditDetailNotaBarangMasuk;
use App\Filament\Resources\DetailNotaBarangMasuks\Pages\ListDetailNotaBarangMasuks;
use App\Filament\Resources\DetailNotaBarangMasuks\Schemas\DetailNotaBarangMasukForm;
use App\Filament\Resources\DetailNotaBarangMasuks\Tables\DetailNotaBarangMasuksTable;
use App\Models\DetailNotaBarangMasuk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DetailNotaBarangMasukResource extends Resource
{
    protected static ?string $model = DetailNotaBarangMasuk::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'BK-BM';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DetailNotaBarangMasukForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailNotaBarangMasuksTable::configure($table);
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
            'index' => ListDetailNotaBarangMasuks::route('/'),
            'create' => CreateDetailNotaBarangMasuk::route('/create'),
            'edit' => EditDetailNotaBarangMasuk::route('/{record}/edit'),
        ];
    }
}
