<?php

namespace App\Filament\Resources\PegawaiDryers;

use App\Filament\Resources\PegawaiDryers\Pages\CreatePegawaiDryer;
use App\Filament\Resources\PegawaiDryers\Pages\EditPegawaiDryer;
use App\Filament\Resources\PegawaiDryers\Pages\ListPegawaiDryers;
use App\Filament\Resources\PegawaiDryers\Schemas\PegawaiDryerForm;
use App\Filament\Resources\PegawaiDryers\Tables\PegawaiDryersTable;
use App\Models\DetailPegawai;
use BackedEnum;
use UnitEnum;   
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiDryerResource extends Resource
{
    protected static ?string $model = DetailPegawai::class;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return PegawaiDryerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiDryersTable::configure($table);
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
            'index' => ListPegawaiDryers::route('/'),
            'create' => CreatePegawaiDryer::route('/create'),
            'edit' => EditPegawaiDryer::route('/{record}/edit'),
        ];
    }
}
