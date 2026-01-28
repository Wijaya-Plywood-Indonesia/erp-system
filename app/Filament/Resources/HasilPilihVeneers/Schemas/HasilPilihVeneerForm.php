<?php

namespace App\Filament\Resources\HasilPilihVeneers\Schemas;

use App\Models\ModalPilihVeneer;
use App\Models\PegawaiPilihVeneer;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

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
                    ->options(function ($livewire) {
                        $produksi = $livewire->getOwnerRecord();
                        if (!$produksi) return [];

                        return ModalPilihVeneer::query()
                            ->where('id_produksi_pilih_veneer', $produksi->id)
                            ->with(['ukuran', 'jenisKayu'])
                            ->get()
                            ->mapWithKeys(function ($item) {
                                $label = "{$item->ukuran->dimensi} | {$item->jenisKayu->nama_kayu} | KW: {$item->kw}";
                                return [$item->id => $label];
                            });
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
                    ->numeric(),
            ]);
    }
}