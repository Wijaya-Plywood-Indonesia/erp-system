<?php

namespace App\Filament\Resources\ProduksiPressDryers;

use App\Filament\Resources\ProduksiPressDryers\Pages\CreateProduksiPressDryer;
use App\Filament\Resources\ProduksiPressDryers\Pages\EditProduksiPressDryer;
use App\Filament\Resources\ProduksiPressDryers\Pages\ListProduksiPressDryers;
use App\Filament\Resources\ProduksiPressDryers\Pages\ViewProduksiPressDryer;
use App\Filament\Resources\ProduksiPressDryers\Schemas\ProduksiPressDryerForm;
use App\Filament\Resources\ProduksiPressDryers\Tables\ProduksiPressDryersTable;
use App\Filament\Resources\ProduksiPressDryers\Schemas\ProduksiPressDryerInfolist;
use App\Models\ProduksiPressDryer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProduksiPressDryerResource extends Resource
{
    protected static ?string $model = ProduksiPressDryer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;
    protected static string|UnitEnum|null $navigationGroup = 'Dryer';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest('created_at');
    }

    public static function form(Schema $schema): Schema
    {
        return ProduksiPressDryerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiPressDryerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiPressDryersTable::configure($table);
    }

public static function getRelations(): array
    {
        return [
            RelationManagers\DetailMesinsRelationManager::class,
            RelationManagers\DetailPegawaisRelationManager::class,
            RelationManagers\DetailMasuksRelationManager::class,
            RelationManagers\DetailHasilsRelationManager::class,
            RelationManagers\ValidasiPressDryersRelationManager::class,
        ];
    }


    public static function getPages(): array
    {
        return [
            'index' => ListProduksiPressDryers::route('/'),
            'create' => CreateProduksiPressDryer::route('/create'),
            'view' => ViewProduksiPressDryer::route('/{record}'),
            'edit' => EditProduksiPressDryer::route('/{record}/edit'),
        ];
    }
}
