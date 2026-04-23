<?php

namespace App\Filament\Resources\RencanaRepairs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\ModalRepair;
use App\Models\RencanaPegawai;
use App\Models\RencanaRepair;
use Filament\Schemas\Components\Utilities\Get;

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
                ->live()
                // --- LOGIKA VALIDASI ANTI-DUPLIKAT ---
                ->rules([
                    fn(Get $get) => function (string $attribute, $value, $fail) use ($get, $record) {
                        $modalId = $get('id_modal_repair');

                        if (!$modalId || !$value) return;

                        $exists = RencanaRepair::where('id_modal_repair', $modalId)
                            ->where('id_rencana_pegawai', $value)
                            // Jika sedang edit, abaikan pengecekan terhadap record diri sendiri
                            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                            ->exists();

                        if ($exists) {
                            $fail('Pegawai ini sudah ditugaskan untuk kayu/palet yang sama.');
                        }
                    },
                ])
                // --- HELPER TEXT DINAMIS ---
                ->helperText(function (Get $get) use ($record) {
                    $modalId = $get('id_modal_repair');
                    $rencanaPegawaiId = $get('id_rencana_pegawai');

                    if ($modalId && $rencanaPegawaiId) {
                        $isDuplicate = RencanaRepair::where('id_modal_repair', $modalId)
                            ->where('id_rencana_pegawai', $rencanaPegawaiId)
                            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                            ->exists();

                        if ($isDuplicate) {
                            return new \Illuminate\Support\HtmlString(
                                '<span class="text-danger-400 font-bold">Pegawai sudah mengerjakan kayu ini di meja lain!</span>'
                            );
                        }
                    }
                })
                ->placeholder('Pilih meja & pegawai...'),


            TextInput::make('kw')
                ->label('KW')
                ->disabled()          // Tidak bisa diubah
                ->dehydrated()        // Tetap tersimpan ke DB
                ->reactive(),

        ]);
    }
}
