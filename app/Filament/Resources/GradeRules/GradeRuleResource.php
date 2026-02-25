<?php

namespace App\Filament\Resources\GradeRules;

use App\Filament\Resources\GradeRules\Pages\CreateGradeRule;
use App\Filament\Resources\GradeRules\Pages\EditGradeRule;
use App\Filament\Resources\GradeRules\Pages\ListGradeRules;
use App\Filament\Resources\GradeRules\Pages\ViewGradeRule;
use App\Filament\Resources\GradeRules\Schemas\GradeRuleForm;
use App\Filament\Resources\GradeRules\Schemas\GradeRuleInfolist;
use App\Filament\Resources\GradeRules\Tables\GradeRulesTable;
use App\Models\GradeRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GradeRuleResource extends Resource
{
    protected static ?string $model = GradeRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'GradeRule';

    public static function form(Schema $schema): Schema
    {
        return GradeRuleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GradeRuleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GradeRulesTable::configure($table);
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
            'index' => ListGradeRules::route('/'),
            'create' => CreateGradeRule::route('/create'),
            'view' => ViewGradeRule::route('/{record}'),
            'edit' => EditGradeRule::route('/{record}/edit'),
        ];
    }
}
