<?php

namespace App\Filament\Resources\VeneerMasuks;

use App\Filament\Resources\VeneerMasuks\Pages\CreateVeneerMasuk;
use App\Filament\Resources\VeneerMasuks\Pages\EditVeneerMasuk;
use App\Filament\Resources\VeneerMasuks\Pages\ListVeneerMasuks;
use App\Filament\Resources\VeneerMasuks\Schemas\VeneerMasukForm;
use App\Filament\Resources\VeneerMasuks\Tables\VeneerMasuksTable;
use App\Models\VeneerMutasi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VeneerMasukResource extends Resource
{
    protected static ?string $model = VeneerMutasi::class;
    protected static ?string $modelLabel = 'Veneer Masuk';
    protected static ?string $pluralModelLabel = 'Veneer Masuk';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static string|UnitEnum|null $navigationGroup = 'BK-BM';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tipe_transaksi', 'masuk');
    }

    public static function form(Schema $schema): Schema
    {
        return VeneerMasukForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VeneerMasuksTable::configure($table);
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
            'index' => ListVeneerMasuks::route('/'),
            'create' => CreateVeneerMasuk::route('/create'),
            'edit' => EditVeneerMasuk::route('/{record}/edit'),
        ];
    }
}
