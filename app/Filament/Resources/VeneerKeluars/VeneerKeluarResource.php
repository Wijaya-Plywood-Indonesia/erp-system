<?php

namespace App\Filament\Resources\VeneerKeluars;

use App\Filament\Resources\VeneerKeluars\Pages\CreateVeneerKeluar;
use App\Filament\Resources\VeneerKeluars\Pages\EditVeneerKeluar;
use App\Filament\Resources\VeneerKeluars\Pages\ListVeneerKeluars;
use App\Filament\Resources\VeneerKeluars\Schemas\VeneerKeluarForm;
use App\Filament\Resources\VeneerKeluars\Tables\VeneerKeluarsTable;
use App\Models\VeneerMutasi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VeneerKeluarResource extends Resource
{
    protected static ?string $model = VeneerMutasi::class;
    protected static ?string $modelLabel = 'Veneer Keluar';
    protected static ?string $pluralModelLabel = 'Veneer Keluar';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string|UnitEnum|null $navigationGroup = 'BK-BM';


    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tipe_transaksi', 'keluar');
    }

    public static function form(Schema $schema): Schema
    {
        return VeneerKeluarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VeneerKeluarsTable::configure($table);
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
            'index' => ListVeneerKeluars::route('/'),
            'create' => CreateVeneerKeluar::route('/create'),
            'edit' => EditVeneerKeluar::route('/{record}/edit'),
        ];
    }
}
