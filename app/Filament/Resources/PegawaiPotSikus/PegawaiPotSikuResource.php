<?php

namespace App\Filament\Resources\PegawaiPotSikus;

use App\Filament\Resources\PegawaiPotSikus\Pages\CreatePegawaiPotSiku;
use App\Filament\Resources\PegawaiPotSikus\Pages\EditPegawaiPotSiku;
use App\Filament\Resources\PegawaiPotSikus\Pages\ListPegawaiPotSikus;
use App\Filament\Resources\PegawaiPotSikus\Schemas\PegawaiPotSikuForm;
use App\Filament\Resources\PegawaiPotSikus\Tables\PegawaiPotSikusTable;
use App\Models\PegawaiPotSiku;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiPotSikuResource extends Resource
{
    protected static ?string $model = PegawaiPotSiku::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiPotSikuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiPotSikusTable::configure($table);
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
            'index' => ListPegawaiPotSikus::route('/'),
            'create' => CreatePegawaiPotSiku::route('/create'),
            'edit' => EditPegawaiPotSiku::route('/{record}/edit'),
        ];
    }
}
