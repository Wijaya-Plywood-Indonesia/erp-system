<?php

namespace App\Filament\Resources\NotaBarangMasuks;

use App\Filament\Resources\NotaBarangMasuks\Pages\CreateNotaBarangMasuk;
use App\Filament\Resources\NotaBarangMasuks\Pages\EditNotaBarangMasuk;
use App\Filament\Resources\NotaBarangMasuks\Pages\ListNotaBarangMasuks;
use App\Filament\Resources\NotaBarangMasuks\Pages\ViewNotaBarangMasuk;
use App\Filament\Resources\NotaBarangMasuks\RelationManagers\DetailBarangMasukRelationManager;
use App\Filament\Resources\NotaBarangMasuks\Schemas\NotaBarangMasukForm;
use App\Filament\Resources\NotaBarangMasuks\Schemas\NotaBarangMasukInfolist;
use App\Filament\Resources\NotaBarangMasuks\Tables\NotaBarangMasuksTable;
use App\Models\NotaBarangMasuk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NotaBarangMasukResource extends Resource
{
    protected static ?string $model = NotaBarangMasuk::class;
    protected static ?string $modelLabel = 'Nota Barang Masuk';
    protected static ?string $pluralModelLabel = 'Nota Barang Masuk';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return NotaBarangMasukForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NotaBarangMasukInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotaBarangMasuksTable::configure($table);
    }
    protected static string|UnitEnum|null $navigationGroup = 'BK-BM';
    public static function getRelations(): array
    {
        return [
                //
            DetailBarangMasukRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotaBarangMasuks::route('/'),
            'create' => CreateNotaBarangMasuk::route('/create'),
            'view' => ViewNotaBarangMasuk::route('/{record}'),
            'edit' => EditNotaBarangMasuk::route('/{record}/edit'),
        ];
    }
}
