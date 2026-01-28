<?php

namespace App\Filament\Resources\HasilPilihVeneers;

use App\Filament\Resources\HasilPilihVeneers\Pages\CreateHasilPilihVeneer;
use App\Filament\Resources\HasilPilihVeneers\Pages\EditHasilPilihVeneer;
use App\Filament\Resources\HasilPilihVeneers\Pages\ListHasilPilihVeneers;
use App\Filament\Resources\HasilPilihVeneers\Schemas\HasilPilihVeneerForm;
use App\Filament\Resources\HasilPilihVeneers\Tables\HasilPilihVeneersTable;
use App\Models\HasilPilihVeneer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HasilPilihVeneerResource extends Resource
{
    protected static ?string $model = HasilPilihVeneer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return HasilPilihVeneerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HasilPilihVeneersTable::configure($table);
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
            'index' => ListHasilPilihVeneers::route('/'),
            'create' => CreateHasilPilihVeneer::route('/create'),
            'edit' => EditHasilPilihVeneer::route('/{record}/edit'),
        ];
    }
}
