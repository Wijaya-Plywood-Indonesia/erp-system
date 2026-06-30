<?php

namespace App\Filament\Resources\Targets;

use App\Filament\Resources\Targets\Pages\CreateTarget;
use App\Filament\Resources\Targets\Pages\EditTarget;
use App\Filament\Resources\Targets\Pages\ListTargets;
use App\Filament\Resources\Targets\Pages\ViewTarget;
use App\Filament\Resources\Targets\Schemas\TargetForm;
use App\Filament\Resources\Targets\Schemas\TargetInfolist;
use App\Filament\Resources\Targets\Tables\TargetsTable;
use App\Models\Target;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TargetResource extends Resource
{
    protected static ?string $model = Target::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Master';

    public static function form(Schema $schema): Schema
    {
        return TargetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TargetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TargetsTable::configure($table);
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
            'index' => ListTargets::route('/'),
            'create' => CreateTarget::route('/create'),
            'view' => ViewTarget::route('/{record}'),
            'edit' => EditTarget::route('/{record}/edit'),
        ];
    }
}
