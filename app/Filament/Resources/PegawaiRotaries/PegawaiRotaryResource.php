<?php

namespace App\Filament\Resources\PegawaiRotaries;

use App\Filament\Resources\PegawaiRotaries\Pages\CreatePegawaiRotary;
use App\Filament\Resources\PegawaiRotaries\Pages\EditPegawaiRotary;
use App\Filament\Resources\PegawaiRotaries\Pages\ListPegawaiRotaries;
use App\Filament\Resources\PegawaiRotaries\Schemas\PegawaiRotaryForm;
use App\Filament\Resources\PegawaiRotaries\Tables\PegawaiRotariesTable;
use App\Models\PegawaiRotary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PegawaiRotaryResource extends Resource
{
    protected static ?string $model = PegawaiRotary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    //grubping
    protected static string|UnitEnum|null $navigationGroup = 'Rotary';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PegawaiRotaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiRotariesTable::configure($table);
    }
    public static function shouldRegisterNavigation(): bool
    {
        return false;
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
            'index' => ListPegawaiRotaries::route('/'),
            'create' => CreatePegawaiRotary::route('/create'),
            'edit' => EditPegawaiRotary::route('/{record}/edit'),
        ];
    }
}
