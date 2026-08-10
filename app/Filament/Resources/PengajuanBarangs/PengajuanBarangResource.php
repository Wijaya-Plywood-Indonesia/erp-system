<?php

namespace App\Filament\Resources\PengajuanBarangs;

use App\Filament\Resources\PengajuanBarangs\Pages\ListPengajuanBarangs;
use App\Filament\Resources\PengajuanBarangs\Pages\CreatePengajuanBarang;
use App\Filament\Resources\PengajuanBarangs\Schemas\PengajuanBarangForm;
use App\Filament\Resources\PengajuanBarangs\Tables\PengajuanBarangsTable;
use App\Models\PengajuanBarang;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class PengajuanBarangResource extends Resource
{
    protected static ?string $model = PengajuanBarang::class;

    protected static ?string $navigationLabel = 'Pengajuan Barang';
    protected static string|UnitEnum|null $navigationGroup = 'Pengajuan';
    protected static ?string $modelLabel = 'Pengajuan Barang';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return PengajuanBarangForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PengajuanBarangsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPengajuanBarangs::route('/'),
            'create' => CreatePengajuanBarang::route('/create'),
        ];
    }
}