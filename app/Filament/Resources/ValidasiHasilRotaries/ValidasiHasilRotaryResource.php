<?php

namespace App\Filament\Resources\ValidasiHasilRotaries;

use App\Filament\Resources\ValidasiHasilRotaries\Pages\CreateValidasiHasilRotary;
use App\Filament\Resources\ValidasiHasilRotaries\Pages\EditValidasiHasilRotary;
use App\Filament\Resources\ValidasiHasilRotaries\Pages\ListValidasiHasilRotaries;
use App\Filament\Resources\ValidasiHasilRotaries\Schemas\ValidasiHasilRotaryForm;
use App\Filament\Resources\ValidasiHasilRotaries\Tables\ValidasiHasilRotariesTable;
use App\Models\ValidasiHasilRotary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ValidasiHasilRotaryResource extends Resource
{
    protected static ?string $model = ValidasiHasilRotary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    //grubping
    protected static string|UnitEnum|null $navigationGroup = 'Rotary';
    protected static ?int $navigationSort = 6;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public static function form(Schema $schema): Schema
    {
        return ValidasiHasilRotaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiHasilRotariesTable::configure($table);
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
            'index' => ListValidasiHasilRotaries::route('/'),
            'create' => CreateValidasiHasilRotary::route('/create'),
            'edit' => EditValidasiHasilRotary::route('/{record}/edit'),
        ];
    }
}
