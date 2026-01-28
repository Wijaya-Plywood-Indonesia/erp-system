<?php

namespace App\Filament\Resources\PegawaiPilihVeneers;

use App\Filament\Resources\PegawaiPilihVeneers\Pages\CreatePegawaiPilihVeneer;
use App\Filament\Resources\PegawaiPilihVeneers\Pages\EditPegawaiPilihVeneer;
use App\Filament\Resources\PegawaiPilihVeneers\Pages\ListPegawaiPilihVeneers;
use App\Filament\Resources\PegawaiPilihVeneers\Schemas\PegawaiPilihVeneerForm;
use App\Filament\Resources\PegawaiPilihVeneers\Tables\PegawaiPilihVeneersTable;
use App\Models\PegawaiPilihVeneer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiPilihVeneerResource extends Resource
{
    protected static ?string $model = PegawaiPilihVeneer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiPilihVeneerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiPilihVeneersTable::configure($table);
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
            'index' => ListPegawaiPilihVeneers::route('/'),
            'create' => CreatePegawaiPilihVeneer::route('/create'),
            'edit' => EditPegawaiPilihVeneer::route('/{record}/edit'),
        ];
    }
}
