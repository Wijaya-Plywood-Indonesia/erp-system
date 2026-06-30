<?php

namespace App\Filament\Resources\VeneerBahanHps;

use App\Filament\Resources\VeneerBahanHps\Pages\CreateVeneerBahanHp;
use App\Filament\Resources\VeneerBahanHps\Pages\EditVeneerBahanHp;
use App\Filament\Resources\VeneerBahanHps\Pages\ListVeneerBahanHps;
use App\Filament\Resources\VeneerBahanHps\Schemas\VeneerBahanHpForm;
use App\Filament\Resources\VeneerBahanHps\Tables\VeneerBahanHpsTable;
use App\Models\VeneerBahanHp;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VeneerBahanHpResource extends Resource
{
    protected static ?string $model = VeneerBahanHp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $recordTitleAttribute = 'Veneer Bahan';

    public static function form(Schema $schema): Schema
    {
        return VeneerBahanHpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VeneerBahanHpsTable::configure($table);
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
            'index' => ListVeneerBahanHps::route('/'),
            'create' => CreateVeneerBahanHp::route('/create'),
            'edit' => EditVeneerBahanHp::route('/{record}/edit'),
        ];
    }
}
