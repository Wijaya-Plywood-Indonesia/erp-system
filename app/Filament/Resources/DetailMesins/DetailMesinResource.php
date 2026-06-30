<?php

namespace App\Filament\Resources\DetailMesins;

use App\Filament\Resources\DetailMesins\Pages\CreateDetailMesin;
use App\Filament\Resources\DetailMesins\Pages\EditDetailMesin;
use App\Filament\Resources\DetailMesins\Pages\ListDetailMesins;
use App\Filament\Resources\DetailMesins\Schemas\DetailMesinForm;
use App\Filament\Resources\DetailMesins\Tables\DetailMesinsTable;
use App\Models\DetailMesin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DetailMesinResource extends Resource
{
    protected static ?string $model = DetailMesin::class;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Dryer';
    public static function form(Schema $schema): Schema
    {
        return DetailMesinForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailMesinsTable::configure($table);
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
            'index' => ListDetailMesins::route('/'),
            'create' => CreateDetailMesin::route('/create'),
            'edit' => EditDetailMesin::route('/{record}/edit'),
        ];
    }
}
