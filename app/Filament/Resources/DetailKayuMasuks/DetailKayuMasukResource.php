<?php

namespace App\Filament\Resources\DetailKayuMasuks;

use App\Filament\Resources\DetailKayuMasuks\Pages\CreateDetailKayuMasuk;
use App\Filament\Resources\DetailKayuMasuks\Pages\EditDetailKayuMasuk;
use App\Filament\Resources\DetailKayuMasuks\Pages\ListDetailKayuMasuks;
use App\Filament\Resources\DetailKayuMasuks\Schemas\DetailKayuMasukForm;
use App\Filament\Resources\DetailKayuMasuks\Tables\DetailKayuMasuksTable;
use App\Models\DetailKayuMasuk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailKayuMasukResource extends Resource
{
    protected static ?string $model = DetailKayuMasuk::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DetailKayuMasukForm::configure($schema);
    }
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public static function table(Table $table): Table
    {
        return DetailKayuMasuksTable::configure($table);
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
            'index' => ListDetailKayuMasuks::route('/'),
            'create' => CreateDetailKayuMasuk::route('/create'),
            'edit' => EditDetailKayuMasuk::route('/{record}/edit'),
        ];
    }
}
