<?php

namespace App\Filament\Resources\ProduksiStiks\RelationManagers;

use App\Filament\Resources\DetailMasuks\Schemas\DetailMasukForm;
use App\Filament\Resources\DetailMasuks\Tables\DetailMasuksTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Concerns\LocksWhenValidated;

/**
 * Modal (bahan masuk) Produksi Stik — SEPENUHNYA MANUAL.
 *
 * Sebelumnya bergantung pada serah terima dari Rotary lewat
 * detail_hasil_palet_rotary_serah_terima_pivot. Sekarang operator Stik
 * mengisi sendiri: nomor palet, jenis kayu, ukuran, KW, dan isi — tidak
 * ada lagi pilihan "palet yang sudah diterima".
 */
class DetailMasukStikRelationManager extends RelationManager
{
    protected static ?string $title = 'Modal';
    protected static string $relationship = 'detailMasukStik';

    use LocksWhenValidated;

    protected string $validasiRelasi = 'validasiStik';

    public function form(Schema $schema): Schema
    {
        $idProduksiStik = $this->getOwnerRecord()->id;
        return DetailMasukForm::configure($schema, $idProduksiStik, 'stik');
    }

    public function table(Table $table): Table
    {
        return DetailMasuksTable::configure($table, tipe: 'stik');
    }
}