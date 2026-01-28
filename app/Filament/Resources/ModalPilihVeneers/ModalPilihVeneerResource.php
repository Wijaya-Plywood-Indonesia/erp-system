<?php

namespace App\Filament\Resources\ModalPilihVeneers;

use App\Filament\Resources\ModalPilihVeneers\Pages\CreateModalPilihVeneer;
use App\Filament\Resources\ModalPilihVeneers\Pages\EditModalPilihVeneer;
use App\Filament\Resources\ModalPilihVeneers\Pages\ListModalPilihVeneers;
use App\Filament\Resources\ModalPilihVeneers\Schemas\ModalPilihVeneerForm;
use App\Filament\Resources\ModalPilihVeneers\Tables\ModalPilihVeneersTable;
use App\Models\ModalPilihVeneer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ModalPilihVeneerResource extends Resource
{
    protected static ?string $model = ModalPilihVeneer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ModalPilihVeneerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModalPilihVeneersTable::configure($table);
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
            'index' => ListModalPilihVeneers::route('/'),
            'create' => CreateModalPilihVeneer::route('/create'),
            'edit' => EditModalPilihVeneer::route('/{record}/edit'),
        ];
    }
}
