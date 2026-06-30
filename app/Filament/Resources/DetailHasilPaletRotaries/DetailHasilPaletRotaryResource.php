<?php

namespace App\Filament\Resources\DetailHasilPaletRotaries;

use App\Filament\Resources\DetailHasilPaletRotaries\Pages\CreateDetailHasilPaletRotary;
use App\Filament\Resources\DetailHasilPaletRotaries\Pages\EditDetailHasilPaletRotary;
use App\Filament\Resources\DetailHasilPaletRotaries\Pages\ListDetailHasilPaletRotaries;
use App\Filament\Resources\DetailHasilPaletRotaries\Schemas\DetailHasilPaletRotaryForm;
use App\Filament\Resources\DetailHasilPaletRotaries\Tables\DetailHasilPaletRotariesTable;
use App\Models\DetailHasilPaletRotary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DetailHasilPaletRotaryResource extends Resource
{

    protected static ?string $model = DetailHasilPaletRotary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;
    //grubping
    protected static string|UnitEnum|null $navigationGroup = 'Rotary';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return DetailHasilPaletRotaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailHasilPaletRotariesTable::configure($table);
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
            'index' => ListDetailHasilPaletRotaries::route('/'),
            'create' => CreateDetailHasilPaletRotary::route('/create'),
            'edit' => EditDetailHasilPaletRotary::route('/{record}/edit'),
        ];
    }
}
