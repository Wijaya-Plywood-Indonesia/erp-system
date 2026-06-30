<?php

namespace App\Filament\Resources\NotaBarangKeluars;

use App\Filament\Resources\NotaBarangKeluars\Pages\CreateNotaBarangKeluar;
use App\Filament\Resources\NotaBarangKeluars\Pages\EditNotaBarangKeluar;
use App\Filament\Resources\NotaBarangKeluars\Pages\ListNotaBarangKeluars;
use App\Filament\Resources\NotaBarangKeluars\Pages\ViewNotaBarangKeluar;
use App\Filament\Resources\NotaBarangKeluars\RelationManagers\DetailRelationManager;
use App\Filament\Resources\NotaBarangKeluars\Schemas\NotaBarangKeluarForm;
use App\Filament\Resources\NotaBarangKeluars\Schemas\NotaBarangKeluarInfolist;
use App\Filament\Resources\NotaBarangKeluars\Tables\NotaBarangKeluarsTable;
use App\Models\NotaBarangKeluar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NotaBarangKeluarResource extends Resource
{
    protected static ?string $model = NotaBarangKeluar::class;
    protected static ?string $modelLabel = 'Nota Barang Keluar';
    protected static ?string $pluralModelLabel = 'Nota Barang Keluar';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return NotaBarangKeluarForm::configure($schema);
    }
    protected static string|UnitEnum|null $navigationGroup = 'BK-BM';
    public static function infolist(Schema $schema): Schema
    {
        return NotaBarangKeluarInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotaBarangKeluarsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
                //
            DetailRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotaBarangKeluars::route('/'),
            'create' => CreateNotaBarangKeluar::route('/create'),
            'view' => ViewNotaBarangKeluar::route('/{record}'),
            'edit' => EditNotaBarangKeluar::route('/{record}/edit'),
        ];
    }
}
