<?php

namespace App\Filament\Resources\KategoriMesins;

use App\Filament\Resources\KategoriMesins\Pages\CreateKategoriMesin;
use App\Filament\Resources\KategoriMesins\Pages\EditKategoriMesin;
use App\Filament\Resources\KategoriMesins\Pages\ListKategoriMesins;
use App\Filament\Resources\KategoriMesins\Pages\ViewKategoriMesin;
use App\Filament\Resources\KategoriMesins\RelationManagers\MesinsRelationManager;
use App\Filament\Resources\KategoriMesins\Schemas\KategoriMesinForm;
use App\Filament\Resources\KategoriMesins\Schemas\KategoriMesinInfolist;
use App\Filament\Resources\KategoriMesins\Tables\KategoriMesinsTable;
use App\Models\KategoriMesin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class KategoriMesinResource extends Resource
{
    protected static ?string $model = KategoriMesin::class;
    //Ngelompokin.
    protected static string|UnitEnum|null $navigationGroup = 'Master';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function form(Schema $schema): Schema
    {
        return KategoriMesinForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KategoriMesinInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KategoriMesinsTable::configure($table);
    }

    //nampilkan data descending
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest('created_at');
    }


    public static function getRelations(): array
    {
        return [
                //
            MesinsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKategoriMesins::route('/'),
            'create' => CreateKategoriMesin::route('/create'),
            'view' => ViewKategoriMesin::route('/{record}'),
            'edit' => EditKategoriMesin::route('/{record}/edit'),
        ];
    }
}
