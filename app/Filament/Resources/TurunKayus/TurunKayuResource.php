<?php

namespace App\Filament\Resources\TurunKayus;

use App\Filament\Resources\TurunKayus\Pages\CreateTurunKayu;
use App\Filament\Resources\TurunKayus\Pages\EditTurunKayu;
use App\Filament\Resources\TurunKayus\Pages\ListTurunKayus;
use App\Filament\Resources\TurunKayus\RelationManagers\DetailTurunKayuRelationManager;
use App\Filament\Resources\TurunKayus\RelationManagers\PegawaiTurunKayuRelationManager;
use App\Filament\Resources\TurunKayus\Schemas\TurunKayuForm;
use App\Filament\Resources\TurunKayus\Schemas\TurunKayusInfolist;
use App\Filament\Resources\TurunKayus\Tables\TurunKayusTable;
use App\Filament\Resources\TurunKayus\Pages\ViewTurunKayu;
use App\Models\TurunKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TurunKayuResource extends Resource
{
    protected static ?string $model = TurunKayu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Kayu';

    public static function form(Schema $schema): Schema
    {
        return TurunKayuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TurunKayusTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TurunKayusInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            PegawaiTurunKayuRelationManager::class,
            DetailTurunKayuRelationManager::class,

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTurunKayus::route('/'),
            'create' => CreateTurunKayu::route('/create'),
            'view' => ViewTurunKayu::route('/{record}'),
            'edit' => EditTurunKayu::route('/{record}/edit'),
        ];
    }
}


