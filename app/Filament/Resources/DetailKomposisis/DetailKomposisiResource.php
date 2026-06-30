<?php

namespace App\Filament\Resources\DetailKomposisis;

use App\Filament\Resources\DetailKomposisis\Pages\CreateDetailKomposisi;
use App\Filament\Resources\DetailKomposisis\Pages\EditDetailKomposisi;
use App\Filament\Resources\DetailKomposisis\Pages\ListDetailKomposisis;
use App\Filament\Resources\DetailKomposisis\Schemas\DetailKomposisiForm;
use App\Filament\Resources\DetailKomposisis\Tables\DetailKomposisisTable;
use App\Models\DetailKomposisi;
use BackedEnum;
use Unitenum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailKomposisiResource extends Resource
{
    protected static ?string $model = DetailKomposisi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'DetailKomposisi';

    public static function form(Schema $schema): Schema
    {
        return DetailKomposisiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailKomposisisTable::configure($table);
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
            'index' => ListDetailKomposisis::route('/'),
            'create' => CreateDetailKomposisi::route('/create'),
            'edit' => EditDetailKomposisi::route('/{record}/edit'),
        ];
    }
}
