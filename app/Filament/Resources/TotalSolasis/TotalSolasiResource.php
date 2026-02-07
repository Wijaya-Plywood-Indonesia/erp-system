<?php

namespace App\Filament\Resources\TotalSolasis;

use App\Filament\Resources\TotalSolasis\Pages\CreateTotalSolasi;
use App\Filament\Resources\TotalSolasis\Pages\EditTotalSolasi;
use App\Filament\Resources\TotalSolasis\Pages\ListTotalSolasis;
use App\Filament\Resources\TotalSolasis\Schemas\TotalSolasiForm;
use App\Filament\Resources\TotalSolasis\Tables\TotalSolasisTable;
use App\Models\TotalSolasi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TotalSolasiResource extends Resource
{
    protected static ?string $model = TotalSolasi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'TotalSolasi';

    public static function form(Schema $schema): Schema
    {
        return TotalSolasiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TotalSolasisTable::configure($table);
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
            'index' => ListTotalSolasis::route('/'),
            'create' => CreateTotalSolasi::route('/create'),
            'edit' => EditTotalSolasi::route('/{record}/edit'),
        ];
    }
}
