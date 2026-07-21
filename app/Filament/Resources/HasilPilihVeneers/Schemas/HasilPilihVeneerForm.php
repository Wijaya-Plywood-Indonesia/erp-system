<?php

namespace App\Filament\Resources\HasilPilihVeneers\Schemas;

use App\Models\ModalPilihVeneer;
use App\Models\PegawaiPilihVeneer;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class HasilPilihVeneerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // SELECT PEGAWAI (Maksimal 2 Orang)
                Select::make('pegawaiPilihVeneers')
                    ->label('Pegawai (Maks 2)')
                    ->relationship('pegawaiPilihVeneers', 'id')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->pegawai->nama_pegawai)
                    ->multiple()
                    ->maxItems(2) // Batasan maksimal 2 orang
                    ->required()
                    ->searchable()
                    ->options(function ($livewire) {
                        $produksi = $livewire->getOwnerRecord();
                        if (!$produksi) return [];

                        return PegawaiPilihVeneer::with('pegawai')
                            ->where('id_produksi_pilih_veneer', $produksi->id)
                            ->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => $p->pegawai->nama_pegawai
                            ]);
                    })
                    ->columnSpanFull(),

                Select::make('id_modal_pilih_veneer')
                    ->label('Pilih Barang Modal')
                    ->required()
                    ->searchable()
                    ->live()
                    ->options(function ($livewire, $record) {
                        $produksi = $livewire->getOwnerRecord();
                        if (!$produksi) return [];

                        return ModalPilihVeneer::query()
                            ->where('id_produksi_pilih_veneer', $produksi->id)
                            ->with(['stokVeneerJadi.jenisKayu', 'hasilPilihVeneers'])
                            ->get()
                            ->filter(function ($item) use ($record) {
                                // Poin 3: modal yang sisanya 0 disembunyikan dari pilihan,
                                // kecuali modal itu memang yang sedang dipakai record ini
                                $sisa = $item->sisaBelumDipakai($record?->id);
                                return $sisa > 0 || ($record && $record->id_modal_pilih_veneer == $item->id);
                            })
                            ->mapWithKeys(function ($item) use ($record) {
                                $stok = $item->stokVeneerJadi;
                                $sisa = $item->sisaBelumDipakai($record?->id);

                                if (!$stok) {
                                    return [$item->id => "Palet {$item->no_palet} · Data Stok N/A [Modal: {$item->jumlah} · Sisa: {$sisa}]"];
                                }

                                $panjang = floatval($stok->panjang);
                                $lebar = floatval($stok->lebar);
                                $tebal = floatval($stok->tebal);

                                $dimensi = "{$panjang} x {$lebar} x {$tebal}";
                                $kayu = $stok->jenisKayu?->nama_kayu ?? '-';

                                // Poin 1: jumlah modal & sisa tampil di label
                                $label = "Palet {$item->no_palet} · {$kayu} · {$dimensi} [KW Asal: {$stok->kw_grade}] · Modal: {$item->jumlah} · Sisa: {$sisa}";
                                return [$item->id => $label];
                            });
                    })
                    ->afterStateUpdated(function (Set $set) {
                        $set('jumlah', null);
                    })
                    ->columnSpanFull(),

                TextInput::make('kw')
                    ->label('KW Hasil')
                    ->required(),

                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->required()
                    ->numeric(),

                TextInput::make('jumlah')
                    ->label('Jumlah Hasil')
                    ->required()
                    ->numeric()
                    ->live(onBlur: true)
                    ->helperText(function (Get $get, $record) {
                        $idModal = $get('id_modal_pilih_veneer');
                        if (!$idModal) return null;

                        $modal = ModalPilihVeneer::find($idModal);
                        if (!$modal) return null;

                        $sisa = $modal->sisaBelumDipakai($record?->id);
                        $input = (float) ($get('jumlah') ?? 0);

                        // Poin 4: melebihi sisa modal
                        if ($input > $sisa) {
                            return "⚠ Stok melebihi batas modal. Sisa yang tersedia: {$sisa} lembar.";
                        }

                        // Poin 2: info sisa setelah dipakai
                        $sisaSetelahInput = $sisa - $input;
                        return "Sisa modal saat ini: {$sisa} lbr. Setelah disimpan: {$sisaSetelahInput} lbr.";
                    })
                    ->rules([
                        fn(Get $get, $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $idModal = $get('id_modal_pilih_veneer');
                            if (!$idModal) return;

                            $modal = ModalPilihVeneer::find($idModal);
                            if (!$modal) return;

                            $sisa = $modal->sisaBelumDipakai($record?->id);

                            if ((float) $value > $sisa) {
                                $fail("Jumlah hasil melebihi sisa modal yang tersedia ({$sisa} lembar).");
                            }
                        },
                    ]),
            ]);
    }
}
