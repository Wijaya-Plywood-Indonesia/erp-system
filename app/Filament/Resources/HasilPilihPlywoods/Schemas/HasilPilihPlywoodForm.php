<?php

namespace App\Filament\Resources\HasilPilihPlywoods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use App\Models\BahanPilihPlywood;
use App\Models\HasilPilihPlywood;

class HasilPilihPlywoodForm
{
    public static function configure(): array
    {
        return [
            Select::make('pegawais')
                ->label('Pegawai')
                ->relationship('pegawais', 'nama_pegawai')
                ->multiple()
                ->maxItems(2)
                ->preload()
                ->searchable()
                ->required()
                ->columnSpanFull(),

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
                            $sudahDiinput = \App\Models\HasilPilihPlywood::where('id_produksi_pilih_plywood', $bahan->id_produksi_pilih_plywood)
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

                            // Total bahan untuk barang spesifik ini
                            $totalBahanBarang = $produksi->bahanPilihPlywood()
                                ->where('id_barang_setengah_jadi_hp', $barangId)
                                ->sum('jumlah');

                            // Total yang sudah diinput sebelumnya (exclude data yang sedang diedit jika perlu)
                            $totalCacatBarang = $produksi->hasilPilihPlywood()
                                ->where('id_barang_setengah_jadi_hp', $barangId)
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