<?php

namespace App\Filament\Resources\PegawaiPilihPlywoods;

use App\Filament\Resources\PegawaiPilihPlywoods\Pages\CreatePegawaiPilihPlywood;
use App\Filament\Resources\PegawaiPilihPlywoods\Pages\EditPegawaiPilihPlywood;
use App\Filament\Resources\PegawaiPilihPlywoods\Pages\ListPegawaiPilihPlywoods;
use App\Filament\Resources\PegawaiPilihPlywoods\Schemas\PegawaiPilihPlywoodForm;
use App\Filament\Resources\PegawaiPilihPlywoods\Tables\PegawaiPilihPlywoodsTable;
use App\Models\PegawaiPilihPlywood;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiPilihPlywoodResource extends Resource
{
    protected static ?string $model = PegawaiPilihPlywood::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiPilihPlywoodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiPilihPlywoodsTable::configure($table);
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
            'index' => ListPegawaiPilihPlywoods::route('/'),
            'create' => CreatePegawaiPilihPlywood::route('/create'),
            'edit' => EditPegawaiPilihPlywood::route('/{record}/edit'),
        ];
    }
}
