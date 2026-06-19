<?php

namespace App\Filament\Resources\TurusanKayus;

use App\Filament\Resources\TurusanKayus\Pages\CreateTurusanKayu;
use App\Filament\Resources\TurusanKayus\Pages\EditTurusanKayu;
use App\Filament\Resources\TurusanKayus\Pages\ListTurusanKayus;
use App\Filament\Resources\TurusanKayus\Pages\ViewTurusanKayu;
use App\Filament\Resources\TurusanKayus\RelationManagers\DetailturusanKayusRelationManager;
use App\Filament\Resources\TurusanKayus\Schemas\TurusanKayuForm;
use App\Filament\Resources\TurusanKayus\Schemas\TurusanKayuInfolist;
use App\Filament\Resources\TurusanKayus\Tables\TurusanKayusTable;
use App\Models\KayuMasuk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TurusanKayuResource extends Resource
{
    protected static ?string $model = KayuMasuk::class;

    protected static ?string $navigationLabel = 'Turusan Kayu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Kayu';

    public static function form(Schema $schema): Schema
    {
        return TurusanKayuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TurusanKayusTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TurusanKayuInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            //
            DetailturusanKayusRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTurusanKayus::route('/'),
            'create' => CreateTurusanKayu::route('/create'),
            'view' => ViewTurusanKayu::route('/{record}'),
            'edit' => EditTurusanKayu::route('/{record}/edit'),
        ];
    }
}
