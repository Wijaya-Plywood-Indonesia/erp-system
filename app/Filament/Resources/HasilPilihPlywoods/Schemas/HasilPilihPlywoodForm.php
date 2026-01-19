<?php

namespace App\Filament\Resources\HasilPilihPlywoods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use App\Models\BahanPilihPlywood;
use App\Models\HasilPilihPlywood;
use App\Models\PegawaiPilihPlywood; // Pastikan model ini di-import
use Illuminate\Database\Eloquent\Builder;

class HasilPilihPlywoodForm
{
    public static function configure(): array
    {
        return [
            // =========================
            // 👷 PEGAWAI (AUTO HIDE DARI DAFTAR ABSEN)
            // =========================
            Select::make('pegawais')
                ->label('Pegawai')
                ->relationship(
                    name: 'pegawais',
                    titleAttribute: 'nama_pegawai',
                    modifyQueryUsing: function (Builder $query, $livewire) {
                        // 1. Ambil ID Produksi Parent
                        $produksiId = $livewire->ownerRecord->id ?? null;

                        // Cek apakah sedang mode EDIT (ambil ID record yang sedang diedit)
                        $currentRecordId = null;
                        if (method_exists($livewire, 'getMountedTableActionRecord')) {
                            $currentRecordId = $livewire->getMountedTableActionRecord()?->id;
                        }

                        if ($produksiId) {
                            $absenIds = PegawaiPilihPlywood::query()
                                ->where('id_produksi_pilih_plywood', $produksiId)
                                ->pluck('id_pegawai')
                                ->toArray();

                            // C. Terapkan Filter (Pakai 'pegawais.id' untuk hindari ambigu)
                            return $query
                                ->whereIn('pegawais.id', $absenIds) // Hanya yang sudah absen
                                // ->whereNotIn('pegawais.id', $usedIds) // Uncomment jika ingin auto-hide yang sudah terpilih
                                ->orderBy('nama_pegawai');
                        }

                        return $query;
                    }
                )
                ->getOptionLabelFromRecordUsing(
                    fn($record) => "{$record->kode_pegawai} - {$record->nama_pegawai}"
                )
                ->searchable(['nama_pegawai', 'kode_pegawai'])
                ->multiple()
                ->preload()
                ->required()
                ->columnSpanFull(),

            // =========================
            // PILIH BARANG (DARI BAHAN)
            // =========================
            Select::make('id_barang_setengah_jadi_hp')
                ->label('Pilih Barang (Dari Bahan)')
                ->required()
                ->searchable()
                ->options(function ($livewire) {
                    $produksiId = $livewire->ownerRecord->id;

                    // Ambil barang yang hanya ada di tabel Bahan untuk produksi ini
                    return BahanPilihPlywood::query()
                        ->where('id_produksi_pilih_plywood', $produksiId)
                        ->with(['barangSetengahJadiHp.ukuran', 'barangSetengahJadiHp.grade', 'barangSetengahJadiHp.jenisBarang'])
                        ->get()
                        ->mapWithKeys(function ($bahan) {
                            $barang = $bahan->barangSetengahJadiHp;

                            // Hitung berapa banyak barang ini yang sudah dicatat sebagai cacat
                            $sudahDiinput = HasilPilihPlywood::where('id_produksi_pilih_plywood', $bahan->id_produksi_pilih_plywood)
                                ->where('id_barang_setengah_jadi_hp', $barang->id)
                                ->sum('jumlah');

                            $sisa = $bahan->jumlah - $sudahDiinput;

                            return [
                                $barang->id => "[Sisa: {$sisa}] " .
                                    ($barang->jenisBarang->nama_jenis_barang ?? '-') . ' | ' .
                                    ($barang->ukuran->nama_ukuran ?? '-') . ' | ' .
                                    ($barang->grade->nama_grade ?? '-')
                            ];
                        });
                })
                ->reactive(),

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

            TextInput::make('jumlah')
                ->label('Jumlah Lembar Cacat')
                ->numeric()
                ->minValue(1)
                ->required()
                ->rules([
                    function ($livewire, $get) {
                        return function ($attribute, $value, $fail) use ($livewire, $get) {
                            $produksi = $livewire->ownerRecord;
                            $barangId = $get('id_barang_setengah_jadi_hp');

                            if (!$barangId) return;

                            // Ambil data record yang sedang diedit (jika ada)
                            // Agar jumlah dia sendiri tidak dihitung ganda saat validasi
                            $currentRecordId = null;
                            if (method_exists($livewire, 'getMountedTableActionRecord')) {
                                $currentRecordId = $livewire->getMountedTableActionRecord()?->id;
                            }

                            // Total bahan untuk barang spesifik ini
                            $totalBahanBarang = $produksi->bahanPilihPlywood()
                                ->where('id_barang_setengah_jadi_hp', $barangId)
                                ->sum('jumlah');

                            // Total yang sudah diinput sebelumnya (exclude diri sendiri saat edit)
                            $totalCacatBarang = $produksi->hasilPilihPlywood()
                                ->where('id_barang_setengah_jadi_hp', $barangId)
                                ->when($currentRecordId, fn($q) => $q->where('id', '!=', $currentRecordId))
                                ->sum('jumlah');

                            if (($totalCacatBarang + $value) > $totalBahanBarang) {
                                $fail("Jumlah melebihi stok bahan untuk barang ini. Sisa tersedia: " . ($totalBahanBarang - $totalCacatBarang));
                            }
                        };
                    },
                ]),

            Textarea::make('ket')
                ->label('Keterangan Tambahan')
                ->placeholder('contoh: Tidak bisa diperbaiki, perbaikan tidak bisa selesai hari itu juga, dll')
                ->columnSpanFull(),
        ];
    }
}
