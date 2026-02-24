<?php

namespace App\Filament\Resources\AkunGroups;

use App\Filament\Resources\AkunGroups\Pages\CreateAkunGroup;
use App\Filament\Resources\AkunGroups\Pages\EditAkunGroup;
use App\Filament\Resources\AkunGroups\Pages\ListAkunGroups;
use App\Filament\Resources\AkunGroups\Pages\ViewAkunGroup;
use App\Filament\Resources\AkunGroups\RelationManagers\AnakAkunsRelationManager;
use App\Filament\Resources\AkunGroups\Schemas\AkunGroupForm;
use App\Filament\Resources\AkunGroups\Schemas\AkunGroupInfolist;
use App\Filament\Resources\AkunGroups\Tables\AkunGroupsTable;
use App\Models\AkunGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AkunGroupResource extends Resource
{
    protected static ?string $model = AkunGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('anakAkuns');
    }
    public static function form(Schema $schema): Schema
    {
        return AkunGroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AkunGroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AkunGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AnakAkunsRelationManager::class,
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAkunGroups::route('/'),
            'create' => CreateAkunGroup::route('/create'),
            'view' => ViewAkunGroup::route('/{record}'),
            'edit' => EditAkunGroup::route('/{record}/edit'),
        ];
    }
}
