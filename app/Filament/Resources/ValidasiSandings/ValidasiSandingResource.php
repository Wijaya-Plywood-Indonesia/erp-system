<?php

namespace App\Filament\Resources\ValidasiSandings;

use App\Filament\Resources\ValidasiSandings\Pages\CreateValidasiSanding;
use App\Filament\Resources\ValidasiSandings\Pages\EditValidasiSanding;
use App\Filament\Resources\ValidasiSandings\Pages\ListValidasiSandings;
use App\Filament\Resources\ValidasiSandings\Schemas\ValidasiSandingForm;
use App\Filament\Resources\ValidasiSandings\Tables\ValidasiSandingsTable;
use App\Models\ValidasiSanding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiSandingResource extends Resource
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static ?string $model = ValidasiSanding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ValidasiSandingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiSandingsTable::configure($table);
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
            'index' => ListValidasiSandings::route('/'),
            'create' => CreateValidasiSanding::route('/create'),
            'edit' => EditValidasiSanding::route('/{record}/edit'),
        ];
    }
}
