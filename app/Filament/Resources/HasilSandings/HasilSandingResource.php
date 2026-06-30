<?php

namespace App\Filament\Resources\HasilSandings;

use App\Filament\Resources\HasilSandings\Pages\CreateHasilSanding;
use App\Filament\Resources\HasilSandings\Pages\EditHasilSanding;
use App\Filament\Resources\HasilSandings\Pages\ListHasilSandings;
use App\Filament\Resources\HasilSandings\Schemas\HasilSandingForm;
use App\Filament\Resources\HasilSandings\Tables\HasilSandingsTable;
use App\Models\HasilSanding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HasilSandingResource extends Resource
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static ?string $model = HasilSanding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return HasilSandingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HasilSandingsTable::configure($table);
    }


    public static function getPages(): array
    {
        return [
            'index' => ListHasilSandings::route('/'),
            'create' => CreateHasilSanding::route('/create'),
            'edit' => EditHasilSanding::route('/{record}/edit'),
        ];
    }
}
