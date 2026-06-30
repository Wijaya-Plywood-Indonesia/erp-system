<?php

namespace App\Filament\Resources\ProduksiPressDryers\RelationManagers;

use App\Filament\Resources\DetailMasuks\Schemas\DetailMasukForm;
use App\Filament\Resources\DetailMasuks\Tables\DetailMasuksTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\Ukuran;
use Illuminate\Support\Facades\DB;

class DetailMasuksRelationManager extends RelationManager
{
    protected static ?string $title = 'Modal';
    protected static string $relationship = 'detailMasuks';

    // FUNGSI BARU UNTUK MEMUNCULKAN TOMBOL DI HALAMAN VIEW
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $idProduksiDryer = $this->getOwnerRecord()->id;

        return DetailMasukForm::configure($schema, $idProduksiDryer);
    }


    public function table(Table $table): Table
    {
        $idProduksiDryer = $this->getOwnerRecord()->id;

        $adaPaletDiterima = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
            ->where('tipe', 'dryer')
            ->exists();

        return DetailMasuksTable::configure($table, $adaPaletDiterima);
    }
}
