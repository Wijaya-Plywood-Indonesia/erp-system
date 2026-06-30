<?php

namespace App\Filament\Resources\KayuMasuks;

use App\Filament\Resources\KayuMasuks\Pages\CreateKayuMasuk;
use App\Filament\Resources\KayuMasuks\Pages\EditKayuMasuk;
use App\Filament\Resources\KayuMasuks\Pages\ListKayuMasuks;
use App\Filament\Resources\KayuMasuks\Pages\ViewKayuMasuk;
use App\Filament\Resources\KayuMasuks\RelationManagers\DetailMasukanKayuRelationManager;
use App\Filament\Resources\KayuMasuks\Schemas\KayuMasukForm;
use App\Filament\Resources\KayuMasuks\Schemas\KayuMasukInfolist;
use App\Filament\Resources\KayuMasuks\Tables\KayuMasuksTable;
use App\Models\KayuMasuk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class KayuMasukResource extends Resource
{
    protected static ?string $model = KayuMasuk::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Kayu';

    public static function form(Schema $schema): Schema
    {
        return KayuMasukForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KayuMasukInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KayuMasuksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
                //
            DetailMasukanKayuRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKayuMasuks::route('/'),
            'create' => CreateKayuMasuk::route('/create'),
            'view' => ViewKayuMasuk::route('/{record}'),
            'edit' => EditKayuMasuk::route('/{record}/edit'),
        ];
    }
}
