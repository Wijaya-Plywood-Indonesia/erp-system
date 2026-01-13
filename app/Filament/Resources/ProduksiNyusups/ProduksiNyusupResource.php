<?php

namespace App\Filament\Resources\ProduksiNyusups;

use App\Filament\Resources\NotaBarangMasuks\RelationManagers\DetailBarangMasukRelationManager;
use App\Filament\Resources\ProduksiNyusups\Pages\CreateProduksiNyusup;
use App\Filament\Resources\ProduksiNyusups\Pages\EditProduksiNyusup;
use App\Filament\Resources\ProduksiNyusups\Pages\ListProduksiNyusups;
use App\Filament\Resources\ProduksiNyusups\Pages\ViewProduksiNyusup;
use App\Filament\Resources\ProduksiNyusups\RelationManagers\PegawaiNyusupRelationManager;
use App\Filament\Resources\ProduksiNyusups\RelationManagers\ValidasiNyusupRelationManager;
use App\Filament\Resources\ProduksiNyusups\RelationManagers\DetailBarangDikerjakanRelationManager;
use App\Filament\Resources\ProduksiNyusups\Schemas\ProduksiNyusupForm;
use App\Filament\Resources\ProduksiNyusups\Schemas\ProduksiNyusupInfolist;
use App\Filament\Resources\ProduksiNyusups\Tables\ProduksiNyusupsTable;
use App\Models\ProduksiNyusup;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduksiNyusupResource extends Resource
{
    protected static ?string $model = ProduksiNyusup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Finishing';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return ProduksiNyusupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiNyusupsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiNyusupInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            PegawaiNyusupRelationManager::class,
            DetailBarangDikerjakanRelationManager::class,
            ValidasiNyusupRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiNyusups::route('/'),
            'create' => CreateProduksiNyusup::route('/create'),
            'view' => ViewProduksiNyusup::route('/{record}'),
            'edit' => EditProduksiNyusup::route('/{record}/edit'),
        ];
    }
}
