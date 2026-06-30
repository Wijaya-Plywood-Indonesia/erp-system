<?php

namespace App\Filament\Resources\DetailLainLains;

use App\Filament\Resources\DetailLainLains\Pages\CreateDetailLainLain;
use App\Filament\Resources\DetailLainLains\Pages\EditDetailLainLain;
use App\Filament\Resources\DetailLainLains\Pages\ListDetailLainLains;
use App\Filament\Resources\DetailLainLains\Pages\ViewDetailLainLain;
use App\Filament\Resources\DetailLainLains\Schemas\DetailLainLainForm;
use App\Filament\Resources\DetailLainLains\Schemas\DetailLainLainInfolist;
use App\Filament\Resources\DetailLainLains\Tables\DetailLainLainsTable;
use App\Models\DetailLainLain;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Resources\DetailLainLains\RelationManagers\LainLainRelationManager;

class DetailLainLainResource extends Resource
{
    protected static ?string $model = DetailLainLain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Lain Lain';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'Detail Lain Lain';


    public static function form(Schema $schema): Schema
    {
        return DetailLainLainForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DetailLainLainInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailLainLainsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LainLainRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDetailLainLains::route('/'),
            'create' => CreateDetailLainLain::route('/create'),
            'view' => ViewDetailLainLain::route('/{record}'),
            'edit' => EditDetailLainLain::route('/{record}/edit'),
        ];
    }
}
