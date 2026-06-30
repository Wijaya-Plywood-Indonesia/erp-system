<?php

namespace App\Filament\Resources\RiwayatKayus;

use App\Filament\Resources\RiwayatKayus\Pages\CreateRiwayatKayu;
use App\Filament\Resources\RiwayatKayus\Pages\EditRiwayatKayu;
use App\Filament\Resources\RiwayatKayus\Pages\ListRiwayatKayus;
use App\Filament\Resources\RiwayatKayus\Schemas\RiwayatKayuForm;
use App\Filament\Resources\RiwayatKayus\Tables\RiwayatKayusTable;
use App\Models\RiwayatKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RiwayatKayuResource extends Resource
{
    protected static ?string $model = RiwayatKayu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Kayu';
    public static function form(Schema $schema): Schema
    {
        return RiwayatKayuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RiwayatKayusTable::configure($table);
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
            'index' => ListRiwayatKayus::route('/'),
            'create' => CreateRiwayatKayu::route('/create'),
            'edit' => EditRiwayatKayu::route('/{record}/edit'),
        ];
    }
}
