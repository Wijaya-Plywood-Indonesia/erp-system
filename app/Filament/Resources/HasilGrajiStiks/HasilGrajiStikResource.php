<?php

namespace App\Filament\Resources\HasilGrajiStiks;

use App\Filament\Resources\HasilGrajiStiks\Pages\CreateHasilGrajiStik;
use App\Filament\Resources\HasilGrajiStiks\Pages\EditHasilGrajiStik;
use App\Filament\Resources\HasilGrajiStiks\Pages\ListHasilGrajiStiks;
use App\Filament\Resources\HasilGrajiStiks\Schemas\HasilGrajiStikForm;
use App\Filament\Resources\HasilGrajiStiks\Tables\HasilGrajiStiksTable;
use App\Models\HasilGrajiStik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HasilGrajiStikResource extends Resource
{
    protected static ?string $model = HasilGrajiStik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'HasilGrajiStik';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return HasilGrajiStikForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HasilGrajiStiksTable::configure($table);
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
            'index' => ListHasilGrajiStiks::route('/'),
            'create' => CreateHasilGrajiStik::route('/create'),
            'edit' => EditHasilGrajiStik::route('/{record}/edit'),
        ];
    }
}
