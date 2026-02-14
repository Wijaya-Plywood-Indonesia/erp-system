<?php

namespace App\Filament\Resources\Pegawais;

use App\Filament\Resources\Pegawais\Pages\CreatePegawai;
use App\Filament\Resources\Pegawais\Pages\EditPegawai;
use App\Filament\Resources\Pegawais\Pages\ListPegawais;
use App\Filament\Resources\Pegawais\Pages\ViewPegawai;
use App\Filament\Resources\Pegawais\Schemas\PegawaiForm;
use App\Filament\Resources\Pegawais\Schemas\PegawaiInfolist;
use App\Filament\Resources\Pegawais\Tables\PegawaisTable;
use App\Models\Pegawai;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PegawaiResource extends Resource
{
    protected static ?string $model = Pegawai::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    //Ngelompokin.
    protected static string|UnitEnum|null $navigationGroup = 'Pegawai & Kontrak';
    protected static ?string $navigationLabel = 'Pegawai';
    protected static ?string $pluralModelLabel = 'Pegawai';
    protected static ?string $modelLabel = 'Pegawai';


    public static function form(Schema $schema): Schema
    {
        return PegawaiForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PegawaiInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaisTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    //ngurutin
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest('created_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPegawais::route('/'),
            'create' => CreatePegawai::route('/create'),
            'view' => ViewPegawai::route('/{record}'),
            'edit' => EditPegawai::route('/{record}/edit'),
        ];
    }
}
