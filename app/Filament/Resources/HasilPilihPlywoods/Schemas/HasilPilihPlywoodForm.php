<?php

namespace App\Filament\Resources\HasilPilihPlywoods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use App\Models\BahanPilihPlywood;
use App\Models\HasilPilihPlywood;
use App\Models\PegawaiPilihPlywood;
use Illuminate\Database\Eloquent\Builder;

class HasilPilihPlywoodForm
{
    public static function configure(): array
    {
        return [
            // =========================
            // 👷 PEGAWAI
            // =========================
            Select::make('pegawais')
                ->label('Pegawai')
                ->relationship(
                    name: 'pegawais',
                    titleAttribute: 'nama_pegawai',
                    modifyQueryUsing: function (Builder $query, $livewire) {
                        $produksiId = $livewire->ownerRecord->id ?? null;
                        if ($produksiId) {
                            $absenIds = PegawaiPilihPlywood::query()
                                ->where('id_produksi_pilih_plywood', $produksiId)
                                ->pluck('id_pegawai')
                                ->toArray();

                            // Pastikan daftar pilihan pegawai diurutkan berdasarkan Nama
                            return $query->whereIn('pegawais.id', $absenIds)->orderBy('nama_pegawai');
                        }
                        return $query;
                    }
                )
                ->getOptionLabelFromRecordUsing(fn($record) => "{$record->kode_pegawai} - {$record->nama_pegawai}")
                ->multiple()
                ->preload()
                ->required()
                ->maxItems(2)
                ->columnSpanFull()
                // PENTING: Mengurutkan ID pegawai sebelum disimpan ke database
                ->dehydrateStateUsing(function ($state) {
                    if (is_array($state)) {
                        sort($state);
                    }
                    return $state;
                }),

            // =========================
            // 📦 PILIH BARANG
            // =========================
            Select::make('id_barang_setengah_jadi_hp')
                ->label('Pilih Barang (Dari Bahan)')
                ->required()
                ->searchable()
                ->options(function ($livewire) {
                    $produksiId = $livewire->ownerRecord->id;

                    return BahanPilihPlywood::query()
                        ->where('id_produksi_pilih_plywood', $produksiId)
                        ->with(['barangSetengahJadiHp.ukuran', 'barangSetengahJadiHp.grade', 'barangSetengahJadiHp.jenisBarang'])
                        ->get()
                        ->mapWithKeys(function ($bahan) {
                            $barang = $bahan->barangSetengahJadiHp;

                            // Hitung TOTAL (Bagus + Cacat) yang sudah masuk DB
                            $sudahDikerjakan = HasilPilihPlywood::where('id_produksi_pilih_plywood', $bahan->id_produksi_pilih_plywood)
                                ->where('id_barang_setengah_jadi_hp', $barang->id)
                                ->selectRaw('SUM(jumlah + jumlah_bagus) as total')
                                ->value('total') ?? 0;

                            $sisa = $bahan->jumlah - $sudahDikerjakan;

                            return [
                                $barang->id => "[Sisa: {$sisa}] " .
                                    ($barang->jenisBarang->nama_jenis_barang ?? '-') . ' | ' .
                                    ($barang->ukuran->nama_ukuran ?? '-') . ' | ' .
                                    ($barang->grade->nama_grade ?? '-')
                            ];
                        });
                })
                ->reactive()
                ->afterStateUpdated(fn($set) => $set('jumlah', 0)),

            Select::make('jenis_cacat')
                ->label('Jenis Cacat')
                ->required()
                ->options([
                    'mengelupas' => 'Mengelupas',
                    'pecah' => 'Pecah',
                    'delaminasi/melembung' => 'Delaminasi / Melembung',
                    'kropos' => 'Kropos',
                    'dll' => 'Lainnya',
                ]),

            Select::make('kondisi')
                ->label('Kondisi')
                ->required()
                ->options([
                    'reject' => 'Reject',
                    'reparasi' => 'Reparasi (Perlu Diperbaiki)',
                ]),

            // =========================
            // 🔢 INPUT JUMLAH
            // =========================
            TextInput::make('jumlah')
                ->label('Jumlah Lembar Cacat')
                ->numeric()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, $get, $set, $livewire) {
                    // Ambil stok awal
                    $stokAwal = \App\Models\BahanPilihPlywood::where('id_produksi_pilih_plywood', $livewire->ownerRecord->id)
                        ->where('id_barang_setengah_jadi_hp', $get('id_barang_setengah_jadi_hp'))
                        ->sum('jumlah');

                    // Ambil yang sudah terpakai di DB
                    $terpakai = \App\Models\HasilPilihPlywood::where('id_produksi_pilih_plywood', $livewire->ownerRecord->id)
                        ->where('id_barang_setengah_jadi_hp', $get('id_barang_setengah_jadi_hp'))
                        ->selectRaw('SUM(jumlah + jumlah_bagus) as total')
                        ->value('total') ?? 0;

                    $sisaSekarang = $stokAwal - $terpakai;

                    // Otomatis isi hasil bagus dengan sisa yang ada dikurangi cacat saat ini
                    $saranBagus = max(0, $sisaSekarang - (int)$state);
                    $set('jumlah_bagus', $saranBagus);
                }),

            TextInput::make('jumlah_bagus')
                ->label('Hasil Bagus (Lembar)')
                ->numeric()
                ->required()
                ->helperText('Isi 0 jika masih ada jenis cacat lain yang ingin diinput.')
                ->reactive(),

            Textarea::make('ket')
                ->label('Keterangan Tambahan')
                ->columnSpanFull(),
        ];
    }
}
