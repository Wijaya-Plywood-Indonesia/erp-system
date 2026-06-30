<?php

namespace App\Filament\Resources\DetailTurunKayus;

use App\Filament\Resources\DetailTurunKayus\Pages\CreateDetailTurunKayu;
use App\Filament\Resources\DetailTurunKayus\Pages\EditDetailTurunKayu;
use App\Filament\Resources\DetailTurunKayus\Pages\ListDetailTurunKayus;
use App\Filament\Resources\DetailTurunKayus\Schemas\DetailTurunKayuForm;
use App\Filament\Resources\DetailTurunKayus\Tables\DetailTurunKayusTable;
use App\Models\DetailTurunKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailTurunKayuResource extends Resource
{
    protected static ?string $model = DetailTurunKayu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DetailTurunKayuForm::configure($schema);
    }
    public static function table(Table $table): Table
    {
        return DetailTurunKayusTable::configure($table);
    }
    public static function shouldRegisterNavigation(): bool
    {
        return false;
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
            'index' => ListDetailTurunKayus::route('/'),
            'create' => CreateDetailTurunKayu::route('/create'),
            'edit' => EditDetailTurunKayu::route('/{record}/edit'),
        ];
    }
}
