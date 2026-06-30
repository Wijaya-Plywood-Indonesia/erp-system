<?php

namespace App\Filament\Resources\ModalSandings;

use App\Filament\Resources\ModalSandings\Pages\CreateModalSanding;
use App\Filament\Resources\ModalSandings\Pages\EditModalSanding;
use App\Filament\Resources\ModalSandings\Pages\ListModalSandings;
use App\Filament\Resources\ModalSandings\Schemas\ModalSandingForm;
use App\Filament\Resources\ModalSandings\Tables\ModalSandingsTable;
use App\Models\ModalSanding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ModalSandingResource extends Resource
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static ?string $model = ModalSanding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ModalSandingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModalSandingsTable::configure($table);
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
            'index' => ListModalSandings::route('/'),
            'create' => CreateModalSanding::route('/create'),
            'edit' => EditModalSanding::route('/{record}/edit'),
        ];
    }
}
