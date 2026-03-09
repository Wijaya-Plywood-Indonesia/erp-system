<?php

namespace App\Filament\Resources\Criterias;

use App\Filament\Resources\Criterias\Pages\CreateCriteria;
use App\Filament\Resources\Criterias\Pages\EditCriteria;
use App\Filament\Resources\Criterias\Pages\ListCriterias;
use App\Filament\Resources\Criterias\Pages\ViewCriteria;
use App\Filament\Resources\Criterias\Schemas\CriteriaForm;
use App\Filament\Resources\Criterias\Schemas\CriteriaInfolist;
use App\Filament\Resources\Criterias\Tables\CriteriasTable;
use App\Models\Criteria;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CriteriaResource extends Resource
{
    protected static ?string $model = Criteria::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Criteria';

    public static function form(Schema $schema): Schema
    {
        return CriteriaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CriteriaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CriteriasTable::configure($table);
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
            'index' => ListCriterias::route('/'),
            'create' => CreateCriteria::route('/create'),
            'view' => ViewCriteria::route('/{record}'),
            'edit' => EditCriteria::route('/{record}/edit'),
        ];
    }
}
