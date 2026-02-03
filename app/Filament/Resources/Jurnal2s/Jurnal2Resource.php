<?php

namespace App\Filament\Resources\Jurnal2s;

use App\Filament\Resources\Jurnal2s\Pages\CreateJurnal2;
use App\Filament\Resources\Jurnal2s\Pages\EditJurnal2;
use App\Filament\Resources\Jurnal2s\Pages\ListJurnal2s;
use App\Filament\Resources\Jurnal2s\Schemas\Jurnal2Form;
use App\Filament\Resources\Jurnal2s\Tables\Jurnal2sTable;
use App\Models\Jurnal2;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class Jurnal2Resource extends Resource
{
    protected static ?string $model = Jurnal2::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';

    protected static ?string $recordTitleAttribute = 'p';

    public static function form(Schema $schema): Schema
    {
        return Jurnal2Form::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Jurnal2sTable::configure($table);
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
            'index' => ListJurnal2s::route('/'),
            'create' => CreateJurnal2::route('/create'),
            'edit' => EditJurnal2::route('/{record}/edit'),
        ];
    }
}
