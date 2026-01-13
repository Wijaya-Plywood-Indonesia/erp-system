<?php

namespace App\Filament\Resources\ProduksiPotJeleks;

use App\Filament\Resources\ProduksiPotJeleks\Pages\CreateProduksiPotJelek;
use App\Filament\Resources\ProduksiPotJeleks\Pages\EditProduksiPotJelek;
use App\Filament\Resources\ProduksiPotJeleks\Pages\ListProduksiPotJeleks;
use App\Filament\Resources\ProduksiPotJeleks\Pages\ViewProduksiPotJelek;
use App\Filament\Resources\ProduksiPotJeleks\Schemas\ProduksiPotJelekForm;
use App\Filament\Resources\ProduksiPotJeleks\Schemas\ProduksiPotJelekInfoList;
use App\Filament\Resources\ProduksiPotJeleks\Tables\ProduksiPotJeleksTable;
use App\Models\ProduksiPotJelek;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduksiPotJelekResource extends Resource
{
    protected static ?string $model = ProduksiPotJelek::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Rotary';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return ProduksiPotJelekForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiPotJeleksTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiPotJelekInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PegawaiPotJelekRelationManager::class,
            RelationManagers\DetailBarangDikerjakanPotJelekRelationManager::class,
            RelationManagers\ValidasiPotJelekRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiPotJeleks::route('/'),
            'create' => CreateProduksiPotJelek::route('/create'),
            'view' => ViewProduksiPotJelek::route('/{record}'),
            'edit' => EditProduksiPotJelek::route('/{record}/edit'),
        ];
    }
}
