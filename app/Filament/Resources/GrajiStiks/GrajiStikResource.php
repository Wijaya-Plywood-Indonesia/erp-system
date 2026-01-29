<?php

namespace App\Filament\Resources\GrajiStiks;

use App\Filament\Resources\GrajiStiks\Pages\CreateGrajiStik;
use App\Filament\Resources\GrajiStiks\Pages\EditGrajiStik;
use App\Filament\Resources\GrajiStiks\Pages\ListGrajiStiks;
use App\Filament\Resources\GrajiStiks\Pages\ViewGrajiStik;
use App\Filament\Resources\GrajiStiks\RelationManagers\HasilGrajiStikRelationManager;
use App\Filament\Resources\GrajiStiks\RelationManagers\ModalGrajiStikRelationManager;
use App\Filament\Resources\GrajiStiks\RelationManagers\PegawaiGrajiStikRelationManager;
use App\Filament\Resources\GrajiStiks\RelationManagers\ValidasiGrajiStikRelationManager;
use App\Filament\Resources\GrajiStiks\Schemas\GrajiStikForm;
use App\Filament\Resources\GrajiStiks\Schemas\GrajiStikInfolist;
use App\Filament\Resources\GrajiStiks\Tables\GrajiStiksTable;
use App\Models\GrajiStik;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;


class GrajiStikResource extends Resource
{
    protected static ?string $model = GrajiStik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'GrajiStik';

    protected static string|UnitEnum|null $navigationGroup = 'Dryer';

    public static function form(Schema $schema): Schema
    {
        return GrajiStikForm::configure($schema);
    }



    public static function table(Table $table): Table
    {
        return GrajiStiksTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GrajiStikInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            PegawaiGrajiStikRelationManager::class,
            ModalGrajiStikRelationManager::class,
            HasilGrajiStikRelationManager::class,
            ValidasiGrajiStikRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrajiStiks::route('/'),
            'create' => CreateGrajiStik::route('/create'),
            'view' => ViewGrajiStik::route('/{record}'),
            'edit' => EditGrajiStik::route('/{record}/edit'),
        ];
    }
}
