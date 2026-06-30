<?php

namespace App\Filament\Resources\DetailPegawaiKedis;

use App\Filament\Resources\DetailPegawaiKedis\Pages\CreateDetailPegawaiKedi;
use App\Filament\Resources\DetailPegawaiKedis\Pages\EditDetailPegawaiKedi;
use App\Filament\Resources\DetailPegawaiKedis\Pages\ListDetailPegawaiKedis;
use App\Filament\Resources\DetailPegawaiKedis\Schemas\DetailPegawaiKediForm;
use App\Filament\Resources\DetailPegawaiKedis\Tables\DetailPegawaiKedisTable;
use App\Models\DetailPegawaiKedi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailPegawaiKediResource extends Resource
{
    protected static ?string $model = DetailPegawaiKedi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DetailPegawaiKediForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailPegawaiKedisTable::configure($table);
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
            'index' => ListDetailPegawaiKedis::route('/'),
            'create' => CreateDetailPegawaiKedi::route('/create'),
            'edit' => EditDetailPegawaiKedi::route('/{record}/edit'),
        ];
    }
}
