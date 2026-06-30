<?php

namespace App\Filament\Resources\PegawaiSandings;

use App\Filament\Resources\PegawaiSandings\Pages\CreatePegawaiSanding;
use App\Filament\Resources\PegawaiSandings\Pages\EditPegawaiSanding;
use App\Filament\Resources\PegawaiSandings\Pages\ListPegawaiSandings;
use App\Filament\Resources\PegawaiSandings\Schemas\PegawaiSandingForm;
use App\Filament\Resources\PegawaiSandings\Tables\PegawaiSandingsTable;
use App\Models\PegawaiSanding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiSandingResource extends Resource
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static ?string $model = PegawaiSanding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PegawaiSandingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiSandingsTable::configure($table);
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
            'index' => ListPegawaiSandings::route('/'),
            'create' => CreatePegawaiSanding::route('/create'),
            'edit' => EditPegawaiSanding::route('/{record}/edit'),
        ];
    }
}
