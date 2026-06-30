<?php

namespace App\Filament\Resources\DetailHasilStiks;

use App\Filament\Resources\DetailHasilStiks\Pages\CreateDetailHasilStik;
use App\Filament\Resources\DetailHasilStiks\Pages\EditDetailHasilStik;
use App\Filament\Resources\DetailHasilStiks\Pages\ListDetailHasilStiks;
use App\Filament\Resources\DetailHasilStiks\Schemas\DetailHasilStikForm;
use App\Filament\Resources\DetailHasilStiks\Tables\DetailHasilStiksTable;
use App\Models\DetailHasilStik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DetailHasilStikResource extends Resource
{
    protected static ?string $model = DetailHasilStik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DetailHasilStikForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailHasilStiksTable::configure($table);
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
            'index' => ListDetailHasilStiks::route('/'),
            'create' => CreateDetailHasilStik::route('/create'),
            'edit' => EditDetailHasilStik::route('/{record}/edit'),
        ];
    }
}
