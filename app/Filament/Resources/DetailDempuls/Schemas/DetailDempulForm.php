<?php

namespace App\Filament\Resources\DetailDempuls\Schemas;

use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\RencanaPegawaiDempul;
use App\Models\DetailDempul; // Pastikan model ini di-import
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Grid;

class DetailDempulForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // =========================
            // BARANG
            // =========================
            Select::make('grade_id')
                ->label('Filter Grade')
                ->options(
                    Grade::whereHas('kategoriBarang', function ($q) {
                        $q->whereIn('nama_kategori', ['PLYWOOD', 'PLATFORM']);
                    })
                        ->orderBy('nama_grade')
                        ->get()
                        ->mapWithKeys(fn($g) => [
                            $g->id => ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                                . ' | ' . $g->nama_grade
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
                        ->whereHas('grade.kategoriBarang', function ($q) {
                            $q->whereIn('nama_kategori', ['PLYWOOD', 'PLATFORM']);
                        })
                        ->joinRelationship('jenisBarang')
                        ->joinRelationship('ukuran');

                    if ($get('grade_id')) {
                        $query->where('barang_setengah_jadi_hp.id_grade', $get('grade_id'));
                    }

                    if ($get('jenis_barang_id_filter')) {
                        $query->where('barang_setengah_jadi_hp.id_jenis_barang', $get('jenis_barang_id_filter'));
                    }

                    $query->orderBy('ukurans.tebal', 'asc')
                        ->orderBy('barang_setengah_jadi_hp.id', 'asc');

                    return $query->get()->mapWithKeys(function ($b) {
                        return [
                            $b->id => ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
                                ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
                                ($b->grade?->nama_grade ?? '-') . ' | ' .
                                ($b->jenisBarang?->nama_jenis_barang ?? '-')
                        ];
                    });
                })
                ->columnSpanFull(),

            TextInput::make('modal')
                ->numeric()
                ->required(),

            TextInput::make('hasil')
                ->numeric()
                ->required(),

            TextInput::make('nomor_palet')
                ->numeric(),

            // =========================
            // 👷 PEGAWAI (AUTO HIDE)
            // =========================
            Select::make('pegawais')
                ->label('Pegawai Dempul')
                ->relationship(
                    name: 'pegawais',
                    titleAttribute: 'nama_pegawai',
                    modifyQueryUsing: function (Builder $query, $livewire) {
                        $produksiId = $livewire->ownerRecord?->id ?? null;

                        $currentRecordId = null;
                        if (method_exists($livewire, 'getMountedTableActionRecord')) {
                            $currentRecordId = $livewire->getMountedTableActionRecord()?->id;
                        }

                        if ($produksiId) {
                            // A. Ambil Pegawai yang TERDAFTAR di Rencana
                            $rencanaIds = RencanaPegawaiDempul::query()
                                ->where('id_produksi_dempul', $produksiId)
                                ->pluck('id_pegawai')
                                ->toArray();

                            // B. Ambil Pegawai yang SUDAH DIPAKAI
                            $usedIds = DetailDempul::query()
                                ->where('id_produksi_dempul', $produksiId)
                                ->when($currentRecordId, fn($q) => $q->where('id', '!=', $currentRecordId))
                                ->with('pegawais:id')
                                ->get()
                                ->flatMap(fn($detail) => $detail->pegawais->pluck('id'))
                                ->toArray();

                            // C. Terapkan Filter dengan NAMA TABEL EKSPLISIT
                            return $query
                                ->whereIn('pegawais.id', $rencanaIds)    // UBAH DARI 'id' KE 'pegawais.id'
                                ->whereNotIn('pegawais.id', $usedIds);   // UBAH DARI 'id' KE 'pegawais.id'
                        }

                        return $query;
                    }
                )
                ->multiple()
                ->required()
                ->maxItems(2)
                ->preload()
                ->searchable()
        ]);
    }
}
