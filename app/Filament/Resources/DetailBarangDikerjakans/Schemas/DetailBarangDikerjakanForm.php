<?php

namespace App\Filament\Resources\DetailBarangDikerjakans\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\PegawaiNyusup;

class DetailBarangDikerjakanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_pegawai_nyusup')
                    ->label('Pegawai')
                    ->required()
                    ->searchable()
                    ->options(function ($livewire) {

                        // 🔑 Ambil Produksi Nyusup (parent)
                        $produksi = $livewire->getOwnerRecord();

                        if (! $produksi) {
                            return [];
                        }

                        return PegawaiNyusup::with('pegawai')
                            ->where('id_produksi_nyusup', $produksi->id)
                            ->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => $p->pegawai->nama_pegawai
                            ]);
                    })
                    ->columnSpanFull(),

                /*
                |--------------------------------------------------------------------------
                | FILTER GRADE (DENGAN KATEGORI)
                |--------------------------------------------------------------------------
                */
                Select::make('grade_id')
                    ->label('Filter Grade')
                    ->options(
                        Grade::whereHas('kategoriBarang', function ($q) {
                            $q->where('nama_kategori', 'PLYWOOD');
                        })
                            ->orderBy('nama_grade')
                            ->get()
                            ->mapWithKeys(fn($g) => [
                                $g->id =>  $g->nama_grade
                            ])
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Grade')
                    ->dehydrated(false),

                Select::make('jenis_barang_id_filter')
                    ->label('Filter Jenis Barang')
                    ->options(
                        JenisBarang::orderBy('nama_jenis_barang')
                            ->pluck('nama_jenis_barang', 'id')
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Jenis Barang')
                    ->dehydrated(false),

                Select::make('id_barang_setengah_jadi_hp')
                    ->label('Barang Setengah Jadi (Plywood)')
                    ->required()
                    ->searchable()
                    ->options(function (callable $get) {

                        $query = BarangSetengahJadiHp::query()
                            ->with([
                                'ukuran',
                                'jenisBarang',
                                'grade.kategoriBarang',
                            ])
                            // 🔒 WAJIB PLYWOOD
                            ->whereHas('grade.kategoriBarang', function ($q) {
                                $q->where('nama_kategori', 'PLYWOOD');
                            })
                            ->joinRelationship('jenisBarang')
                            ->joinRelationship('ukuran');

                        // ✅ FILTER GRADE
                        if ($get('grade_id')) {
                            $query->where('barang_setengah_jadi_hp.id_grade', $get('grade_id'));
                        }

                        // ✅ FILTER JENIS BARANG (INI YANG KURANG!)
                        if ($get('jenis_barang_id_filter')) {
                            $query->where(
                                'barang_setengah_jadi_hp.id_jenis_barang',
                                $get('jenis_barang_id_filter')
                            );
                        }

                        $query
                            ->orderBy('ukurans.tebal', 'asc')
                            ->orderBy('barang_setengah_jadi_hp.id', 'asc');

                        return $query->get()->mapWithKeys(function ($b) {
    return [
        $b->id =>
            ($b->ukuran?->tebal ?? '-') . ' | ' .
            ($b->grade?->nama_grade ?? '-') . ' | ' .
            ($b->jenisBarang?->nama_jenis_barang ?? '-')
    ];
});

                    })
                    ->columnSpanFull(),

                TextInput::make('modal')
                    ->label('Modal Nyusup')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                TextInput::make('hasil')
                    ->label('Hasil Nyusup')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                TextInput::make('no_palet')
                    ->label('No Palet')
                    ->numeric()
                    ->required(),
            ]);
    }
}
