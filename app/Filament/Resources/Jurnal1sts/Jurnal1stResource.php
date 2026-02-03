<?php

namespace App\Filament\Resources\Jurnal1sts;

use App\Filament\Resources\Jurnal1sts\Pages\CreateJurnal1st;
use App\Filament\Resources\Jurnal1sts\Pages\EditJurnal1st;
use App\Filament\Resources\Jurnal1sts\Pages\ListJurnal1sts;
use App\Filament\Resources\Jurnal1sts\Schemas\Jurnal1stForm;
use App\Filament\Resources\Jurnal1sts\Tables\Jurnal1stsTable;
use App\Models\Jurnal1st;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class Jurnal1stResource extends Resource
{
    protected static ?string $model = Jurnal1st::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $navigationLabel = 'Jurnal 1st';
    protected static ?string $pluralModelLabel = 'Jurnal 1st';
    protected static ?string $modelLabel = 'Jurnal 1st';

    public static function form(Schema $schema): Schema
    {
        return Jurnal1stForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Jurnal1stsTable::configure($table);
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
            'index' => ListJurnal1sts::route('/'),
            'create' => CreateJurnal1st::route('/create'),
            'edit' => EditJurnal1st::route('/{record}/edit'),
        ];
    }
}