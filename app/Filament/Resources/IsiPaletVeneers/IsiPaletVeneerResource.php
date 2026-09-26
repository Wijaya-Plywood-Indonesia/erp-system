<?php

namespace App\Filament\Resources\IsiPaletVeneers;

use App\Filament\Resources\IsiPaletVeneers\Pages\CreateIsiPaletVeneer;
use App\Filament\Resources\IsiPaletVeneers\Pages\EditIsiPaletVeneer;
use App\Filament\Resources\IsiPaletVeneers\Pages\ListIsiPaletVeneers;
use App\Filament\Resources\IsiPaletVeneers\Schemas\IsiPaletVeneerForm;
use App\Filament\Resources\IsiPaletVeneers\Tables\IsiPaletVeneersTable;
use App\Models\IsiPaletVeneer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IsiPaletVeneerResource extends Resource
{
    protected static ?string $model = IsiPaletVeneer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'IsiPaletVeneer';

    public static function form(Schema $schema): Schema
    {
        return IsiPaletVeneerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IsiPaletVeneersTable::configure($table);
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
            'index' => ListIsiPaletVeneers::route('/'),
            'create' => CreateIsiPaletVeneer::route('/create'),
            'edit' => EditIsiPaletVeneer::route('/{record}/edit'),
        ];
    }
}
