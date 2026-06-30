<?php

namespace App\Filament\Resources\DetailTurusanKayus;

use App\Filament\Resources\DetailTurusanKayus\Pages\CreateDetailTurusanKayu;
use App\Filament\Resources\DetailTurusanKayus\Pages\EditDetailTurusanKayu;
use App\Filament\Resources\DetailTurusanKayus\Pages\ListDetailTurusanKayus;
use App\Filament\Resources\DetailTurusanKayus\Schemas\DetailTurusanKayuForm;
use App\Filament\Resources\DetailTurusanKayus\Tables\DetailTurusanKayusTable;
use App\Filament\Resources\TurusanKayus\RelationManagers\DetailturusanKayusRelationManager;
use App\Models\DetailTurusanKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailTurusanKayuResource extends Resource
{
    protected static ?string $model = DetailTurusanKayu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DetailTurusanKayuForm::configure($schema);
    }
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return DetailTurusanKayusTable::configure($table);
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
            'index' => ListDetailTurusanKayus::route('/'),
            'create' => CreateDetailTurusanKayu::route('/create'),
            'edit' => EditDetailTurusanKayu::route('/{record}/edit'),
        ];
    }
}
