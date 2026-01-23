<?php

namespace App\Filament\Resources\RencanaPegawais\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema; // PAKAI Form, bukan Schema!
use App\Models\Pegawai;
use App\Models\RencanaPegawai;

class RencanaPegawaiForm
{
    public static function configure(Schema $form, $record = null): Schema
    {
        // Ambil ID produksi dari owner (RelationManager) atau dari record
        $produksiId = $record?->id_produksi_repair
            ?? request()->query('produksi_id')
            ?? $form->getLivewire()->ownerRecord?->id
            ?? request()->route('record');

        // NOMOR MEJA TERAKHIR → otomatis +1
        $lastMeja = RencanaPegawai::where('id_produksi_repair', $produksiId)->max('nomor_meja');

        // PEGAWAI YANG SUDAH DITUGASKAN → HILANG DARI DROPDOWN!
        $usedPegawaiIds = RencanaPegawai::where('id_produksi_repair', $produksiId)
            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
            ->pluck('id_pegawai')
            ->toArray();

        return $form->schema([

            TimePicker::make('jam_masuk')
                ->label('Jam Masuk')
                ->default('06:00')
                ->seconds(false)
                ->required(),

            TimePicker::make('jam_pulang')
                ->label('Jam Pulang')
                ->default('16:00')
                ->seconds(false)
                ->required(),

            Select::make('id_pegawai')
                ->label('Pegawai')
                ->options(function ($livewire) {
                    $produksiId = $livewire->ownerRecord?->id;

                    // Ambil record yang sedang diedit dari Livewire
                    $editingRecord = method_exists($livewire, 'getMountedTableActionRecord')
                        ? $livewire->getMountedTableActionRecord()
                        : null;

                    $usedPegawaiIds = RencanaPegawai::where('id_produksi_repair', $produksiId)
                        ->when($editingRecord, fn($q) => $q->where('id', '!=', $editingRecord->id))
                        ->pluck('id_pegawai')
                        ->toArray();

                    return Pegawai::whereNotIn('id', $usedPegawaiIds)
                        ->get()
                        ->mapWithKeys(fn($p) => [$p->id => "{$p->kode_pegawai} - {$p->nama_pegawai}"]);
                })
                ->searchable()
                ->required()
                ->rules([
                    fn($livewire) => function ($attribute, $value, $fail) use ($livewire) {
                        $editingRecord = method_exists($livewire, 'getMountedTableActionRecord')
                            ? $livewire->getMountedTableActionRecord()
                            : null;

                        $exists = RencanaPegawai::where('id_produksi_repair', $livewire->ownerRecord?->id)
                            ->where('id_pegawai', $value)
                            ->when($editingRecord, fn($q) => $q->where('id', '!=', $editingRecord->id))
                            ->exists();

                        if ($exists) $fail('Pegawai sudah ditugaskan.');
                    }
                ]),

            TextInput::make('nomor_meja')
                ->label('Nomor Meja')
                ->numeric()
                ->required()
                ->rules([
                    fn($livewire) => function ($attribute, $value, $fail) use ($livewire) {
                        $editingRecord = method_exists($livewire, 'getMountedTableActionRecord')
                            ? $livewire->getMountedTableActionRecord()
                            : null;

                        $count = RencanaPegawai::where('id_produksi_repair', $livewire->ownerRecord?->id)
                            ->where('nomor_meja', $value)
                            ->when($editingRecord, fn($q) => $q->where('id', '!=', $editingRecord->id))
                            ->count();

                        if ($count >= 2) $fail("Meja nomor {$value} sudah penuh (maksimal 2 orang).");
                    }
                ])
        ]);
    }
}
