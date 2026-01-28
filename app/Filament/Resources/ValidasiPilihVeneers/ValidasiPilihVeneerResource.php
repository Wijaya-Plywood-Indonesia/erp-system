<?php

namespace App\Filament\Resources\ValidasiPilihVeneers;

use App\Filament\Resources\ValidasiPilihVeneers\Pages\CreateValidasiPilihVeneer;
use App\Filament\Resources\ValidasiPilihVeneers\Pages\EditValidasiPilihVeneer;
use App\Filament\Resources\ValidasiPilihVeneers\Pages\ListValidasiPilihVeneers;
use App\Filament\Resources\ValidasiPilihVeneers\Schemas\ValidasiPilihVeneerForm;
use App\Filament\Resources\ValidasiPilihVeneers\Tables\ValidasiPilihVeneersTable;
use App\Models\ValidasiPilihVeneer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiPilihVeneerResource extends Resource
{
    protected static ?string $model = ValidasiPilihVeneer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiPilihVeneerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiPilihVeneersTable::configure($table);
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
            'index' => ListValidasiPilihVeneers::route('/'),
            'create' => CreateValidasiPilihVeneer::route('/create'),
            'edit' => EditValidasiPilihVeneer::route('/{record}/edit'),
        ];
    }
}
