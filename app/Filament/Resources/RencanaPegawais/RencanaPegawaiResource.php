<?php

namespace App\Filament\Resources\RencanaPegawais;

use App\Filament\Resources\RencanaPegawais\Pages\CreateRencanaPegawai;
use App\Filament\Resources\RencanaPegawais\Pages\EditRencanaPegawai;
use App\Filament\Resources\RencanaPegawais\Pages\ListRencanaPegawais;
use App\Filament\Resources\RencanaPegawais\Schemas\RencanaPegawaiForm;
use App\Filament\Resources\RencanaPegawais\Tables\RencanaPegawaisTable;
use App\Models\RencanaPegawai;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RencanaPegawaiResource extends Resource
{
    protected static ?string $model = RencanaPegawai::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public static function form(Schema $schema): Schema
    {
        return RencanaPegawaiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RencanaPegawaisTable::configure($table);
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
            'index' => ListRencanaPegawais::route('/'),
            'create' => CreateRencanaPegawai::route('/create'),
            'edit' => EditRencanaPegawai::route('/{record}/edit'),
        ];
    }
}
