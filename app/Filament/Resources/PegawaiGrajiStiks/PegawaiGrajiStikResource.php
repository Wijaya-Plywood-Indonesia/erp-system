<?php

namespace App\Filament\Resources\PegawaiGrajiStiks;

use App\Filament\Resources\PegawaiGrajiStiks\Pages\CreatePegawaiGrajiStik;
use App\Filament\Resources\PegawaiGrajiStiks\Pages\EditPegawaiGrajiStik;
use App\Filament\Resources\PegawaiGrajiStiks\Pages\ListPegawaiGrajiStiks;
use App\Filament\Resources\PegawaiGrajiStiks\Schemas\PegawaiGrajiStikForm;
use App\Filament\Resources\PegawaiGrajiStiks\Tables\PegawaiGrajiStiksTable;
use App\Models\PegawaiGrajiStik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiGrajiStikResource extends Resource
{
    protected static ?string $model = PegawaiGrajiStik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'PegawaiGrajiStik';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiGrajiStikForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiGrajiStiksTable::configure($table);
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
            'index' => ListPegawaiGrajiStiks::route('/'),
            'create' => CreatePegawaiGrajiStik::route('/create'),
            'edit' => EditPegawaiGrajiStik::route('/{record}/edit'),
        ];
    }
}
