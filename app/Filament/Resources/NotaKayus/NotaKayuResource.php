<?php

namespace App\Filament\Resources\NotaKayus;

use App\Filament\Resources\NotaKayus\Pages\CreateNotaKayu;
use App\Filament\Resources\NotaKayus\Pages\EditNotaKayu;
use App\Filament\Resources\NotaKayus\Pages\ListNotaKayus;
use App\Filament\Resources\NotaKayus\Pages\ViewNotaKayu;
use App\Filament\Resources\NotaKayus\RelationManagers\KayuMasukRelationManager;
use App\Filament\Resources\NotaKayus\Schemas\NotaKayuForm;
use App\Filament\Resources\NotaKayus\Schemas\NotaKayuInfolist;
use App\Filament\Resources\NotaKayus\Tables\NotaKayusTable;
use App\Models\NotaKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NotaKayuResource extends Resource
{
    protected static ?string $model = NotaKayu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Kayu';
    public static function form(Schema $schema): Schema
    {
        return NotaKayuForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NotaKayuInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotaKayusTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
                //
            KayuMasukRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotaKayus::route('/'),
            'create' => CreateNotaKayu::route('/create'),
            'view' => ViewNotaKayu::route('/{record}'),
            'edit' => EditNotaKayu::route('/{record}/edit'),
        ];
    }
}
