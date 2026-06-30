<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries;

use App\Filament\Resources\PenggunaanLahanRotaries\Pages\CreatePenggunaanLahanRotary;
use App\Filament\Resources\PenggunaanLahanRotaries\Pages\EditPenggunaanLahanRotary;
use App\Filament\Resources\PenggunaanLahanRotaries\Pages\ListPenggunaanLahanRotaries;
use App\Filament\Resources\PenggunaanLahanRotaries\Schemas\PenggunaanLahanRotaryForm;
use App\Filament\Resources\PenggunaanLahanRotaries\Tables\PenggunaanLahanRotariesTable;
use App\Models\PenggunaanLahanRotary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PenggunaanLahanRotaryResource extends Resource
{
    protected static ?string $model = PenggunaanLahanRotary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;
    //grubping
    protected static string|UnitEnum|null $navigationGroup = 'Rotary';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return PenggunaanLahanRotaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PenggunaanLahanRotariesTable::configure($table);
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
            'index' => ListPenggunaanLahanRotaries::route('/'),
            'create' => CreatePenggunaanLahanRotary::route('/create'),
            'edit' => EditPenggunaanLahanRotary::route('/{record}/edit'),
        ];
    }
}
