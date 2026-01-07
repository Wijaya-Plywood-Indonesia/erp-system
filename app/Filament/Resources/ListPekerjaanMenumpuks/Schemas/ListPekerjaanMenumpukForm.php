<?php

namespace App\Filament\Resources\ListPekerjaanMenumpuks\Schemas;

use App\Models\BahanPilihPlywood;
use App\Models\ListPekerjaanMenumpuk;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class ListPekerjaanMenumpukForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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

            TextInput::make('jumlah')
                    ->label('Jumlah')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),
            ]);
    }
}
