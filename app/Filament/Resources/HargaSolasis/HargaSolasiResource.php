<?php

namespace App\Filament\Resources\HargaSolasis;

use App\Filament\Resources\HargaSolasis\Pages\CreateHargaSolasi;
use App\Filament\Resources\HargaSolasis\Pages\EditHargaSolasi;
use App\Filament\Resources\HargaSolasis\Pages\ListHargaSolasis;
use App\Filament\Resources\HargaSolasis\Schemas\HargaSolasiForm;
use App\Filament\Resources\HargaSolasis\Tables\HargaSolasisTable;
use App\Models\HargaSolasi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HargaSolasiResource extends Resource
{
    protected static ?string $model = HargaSolasi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'HargaSolasi';

    public static function form(Schema $schema): Schema
    {
        return HargaSolasiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HargaSolasisTable::configure($table);
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
            'index' => ListHargaSolasis::route('/'),
            'create' => CreateHargaSolasi::route('/create'),
            'edit' => EditHargaSolasi::route('/{record}/edit'),
        ];
    }
}
