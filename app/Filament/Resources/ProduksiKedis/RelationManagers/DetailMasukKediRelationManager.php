<?php

namespace App\Filament\Resources\ProduksiKedis\RelationManagers;

use App\Filament\Resources\DetailMasukKedis\DetailMasukKediResource;
use App\Filament\Resources\DetailMasukKedis\Schemas\DetailMasukKediForm;
use App\Filament\Resources\DetailMasukKedis\Tables\DetailMasukKedisTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Concerns\LocksWhenValidated;

class DetailMasukKediRelationManager extends RelationManager
{
    protected static ?string $title = 'Masuk Kedi';
    protected static string $relationship = 'detailMasukKedi';

    use LocksWhenValidated;

    protected string $validasiRelasi = 'validasiKedi';
    // Tidak diisi $validasiTipe: validasi Kedi cuma ada satu jenis, yaitu
    // tipe 'bongkar' (lihat ValidasiKediForm — field 'tipe' selalu
    // di-hardcode 'bongkar', tidak pernah ada 'masuk'). Jadi kuncinya
    // cukup cek "ada validasi divalidasi apa pun" tanpa filter tipe.


    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        return true;
    }


    public function form(Schema $schema): Schema
    {
        return DetailMasukKediForm::configure($schema, $this->getOwnerRecord()->id);
    }
    public function table(Table $table): Table
    {
        return DetailMasukKedisTable::configure($table);
    }

}