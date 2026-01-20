<?php

namespace App\Filament\Resources\ProduksiKedis;

use App\Filament\Resources\ProduksiKedis\Pages\CreateProduksiKedi;
use App\Filament\Resources\ProduksiKedis\Pages\EditProduksiKedi;
use App\Filament\Resources\ProduksiKedis\Pages\ListProduksiKedis;
use App\Filament\Resources\ProduksiKedis\Pages\ViewProduksiKedi;
use App\Filament\Resources\ProduksiKedis\RelationManagers\DetailBongkarRelationManager;
use App\Filament\Resources\ProduksiKedis\RelationManagers\DetailMasukKediRelationManager;
use App\Filament\Resources\ProduksiKedis\RelationManagers\PegawaiKediRelationManager;
use App\Filament\Resources\ProduksiKedis\RelationManagers\YesRelationManager;
use App\Filament\Resources\ProduksiKedis\Schemas\ProduksiKediForm;
use App\Filament\Resources\ProduksiKedis\Schemas\ProduksiKediInfolist;
use App\Filament\Resources\ProduksiKedis\Tables\ProduksiKedisTable;
use App\Models\DetailPegawaiKedi;
use App\Models\ProduksiKedi;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Route;

class ProduksiKediResource extends Resource
{
    protected static ?string $model = ProduksiKedi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Dryer';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ProduksiKediForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiKediInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiKedisTable::configure($table);
    }
    public static function getRelations(): array
    {
        return [
            DetailMasukKediRelationManager::class,
            DetailBongkarRelationManager::class,
            PegawaiKediRelationManager::class,
            YesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiKedis::route('/'),
            'create' => CreateProduksiKedi::route('/create'),
            'view' => ViewProduksiKedi::route('/{record}'),
            'edit' => EditProduksiKedi::route('/{record}/edit'),
        ];
    }
}
