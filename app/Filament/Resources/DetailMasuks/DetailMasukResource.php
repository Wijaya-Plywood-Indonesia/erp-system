<?php

namespace App\Filament\Resources\DetailMasuks;

use App\Filament\Resources\DetailMasuks\Pages\CreateDetailMasuk;
use App\Filament\Resources\DetailMasuks\Pages\EditDetailMasuk;
use App\Filament\Resources\DetailMasuks\Pages\ListDetailMasuks;
use App\Filament\Resources\DetailMasuks\Schemas\DetailMasukForm;
use App\Filament\Resources\DetailMasuks\Tables\DetailMasuksTable;
use App\Models\DetailMasuk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DetailMasukResource extends Resource
{
    protected static ?string $model = DetailMasuk::class;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Dryer';

    public static function form(Schema $schema): Schema
    {
        return DetailMasukForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailMasuksTable::configure($table);
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
            'index' => ListDetailMasuks::route('/'),
            'create' => CreateDetailMasuk::route('/create'),
            'edit' => EditDetailMasuk::route('/{record}/edit'),
        ];
    }
}
