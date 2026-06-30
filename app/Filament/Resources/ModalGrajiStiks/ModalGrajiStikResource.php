<?php

namespace App\Filament\Resources\ModalGrajiStiks;

use App\Filament\Resources\ModalGrajiStiks\Pages\CreateModalGrajiStik;
use App\Filament\Resources\ModalGrajiStiks\Pages\EditModalGrajiStik;
use App\Filament\Resources\ModalGrajiStiks\Pages\ListModalGrajiStiks;
use App\Filament\Resources\ModalGrajiStiks\Schemas\ModalGrajiStikForm;
use App\Filament\Resources\ModalGrajiStiks\Tables\ModalGrajiStiksTable;
use App\Models\ModalGrajiStik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ModalGrajiStikResource extends Resource
{
    protected static ?string $model = ModalGrajiStik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'ModalGrajiStik';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ModalGrajiStikForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModalGrajiStiksTable::configure($table);
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
            'index' => ListModalGrajiStiks::route('/'),
            'create' => CreateModalGrajiStik::route('/create'),
            'edit' => EditModalGrajiStik::route('/{record}/edit'),
        ];
    }
}
