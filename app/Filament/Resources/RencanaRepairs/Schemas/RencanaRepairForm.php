<?php

namespace App\Filament\Resources\RencanaRepairs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\ModalRepair;
use App\Models\RencanaPegawai;
use App\Models\RencanaRepair;

class RencanaRepairForm
{
    public static function configure(Schema $schema, $record = null): Schema
    {
        $produksiId = $record?->id_produksi_repair
            ?? request()->query('produksi_id')
            ?? $schema->getLivewire()->ownerRecord?->id
            ?? request()->route('record');

        return $schema->schema([

            Select::make('id_modal_repair')
                ->label('Pilih Kayu (Ukuran - Jenis - KW)')
                ->options(function () use ($produksiId) {
                    return ModalRepair::where('id_produksi_repair', $produksiId)
                        ->with(['ukuran', 'jenisKayu'])
                        ->get()
                        ->mapWithKeys(fn($modal) => [
                            $modal->id => sprintf(
                                '%s | %s | %s',
                                $modal->ukuran->dimensi ?? '-',
                                $modal->jenisKayu->nama_kayu ?? '-',
                                'Palet - ' . ($modal->nomor_palet ?? '-')
                            )
                        ]);
                })
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                ->afterStateUpdated(function (callable $set, $state) {
                    if ($state) {
                        $modal = ModalRepair::find($state);
                        $set('kw', $modal?->kw); // ← Otomatis mengisi KW
                    } else {
                        $set('kw', null);
                    }
                })
                ->placeholder('Pilih Modal Repair'),

            Select::make('id_rencana_pegawai')
                ->label('Penempatan Meja & Pegawai')
                ->options(function () use ($produksiId) {
                    return RencanaPegawai::where('id_produksi_repair', $produksiId)
                        ->with('pegawai')
                        ->orderBy('nomor_meja')
                        ->get()
                        ->mapWithKeys(fn($rp) => [
                            $rp->id => sprintf(
                                'Meja %s - %s (%s)',
                                $rp->nomor_meja,
                                $rp->pegawai?->nama_pegawai ?? '-',
                                $rp->pegawai?->kode_pegawai ?? '-'
                            )
                        ]);
                })
                ->searchable()
                ->preload()
                ->required()
                ->placeholder('Pilih meja & pegawai...'),


            TextInput::make('kw')
                ->label('KW')
                ->disabled()          // Tidak bisa diubah
                ->dehydrated()        // Tetap tersimpan ke DB
                ->reactive(),

        ]);
    }
}
