<?php

namespace App\Filament\Resources\RencanaPegawaiDempuls\Schemas;

use Filament\Schemas\Schema;
use App\Models\Pegawai;
use App\Models\RencanaPegawaiDempul;
use Filament\Forms\Components\Select;
use Carbon\CarbonPeriod;

class RencanaPegawaiDempulForm
{
    public static function configure(Schema $schema, $record = null): Schema
    {
        // Ambil ID produksi dari owner (RelationManager) atau dari record
        $produksiId = $record?->id_produksi_dempul
            ?? request()->query('produksi_id')
            ?? $schema->getLivewire()->ownerRecord?->id
            ?? request()->route('record');

        // PEGAWAI YANG SUDAH DITUGASKAN → HILANG DARI DROPDOWN!
        $usedPegawaiIds = RencanaPegawaiDempul::where('id_produksi_dempul', $produksiId)
            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
            ->pluck('id_pegawai')
            ->toArray();

        return $schema
            ->components([
                Select::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->options(self::timeOptions())
                    ->default('06:00') // Default: 06:00 (sore)
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null), // Tampilkan hanya HH:MM,
                Select::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->options(self::timeOptions())
                    ->default('16:00') // Default: 17:00 (sore)
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null), // Tampilkan hanya HH:MM,

                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->options(function () use ($usedPegawaiIds) {
                        return Pegawai::whereNotIn('id', $usedPegawaiIds)
                            ->orderBy('kode_pegawai')
                            ->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => "{$p->kode_pegawai} - {$p->nama_pegawai}"
                            ])
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Pilih pegawai...')
                    ->reactive()
                    ->rules([
                        fn() => function ($attribute, $value, $fail) use ($usedPegawaiIds) {
                            if (in_array($value, $usedPegawaiIds)) {
                                $fail('Pegawai ini sudah ditugaskan hari ini!');
                            }
                        }
                    ]),
            ]);
    }

    public static function timeOptions(): array
    {
        return collect(CarbonPeriod::create('00:00', '1 hour', '23:00')->toArray())
            ->mapWithKeys(fn($time) => [
                $time->format('H:i') => $time->format('H.i'),
            ])
            ->toArray();
    }
}
