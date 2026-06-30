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
    ->searchable()
    ->preload()
    ->required()
    ->options(
        Pegawai::query()
            ->get()
            ->mapWithKeys(fn($p) => [
                $p->id => "{$p->kode_pegawai} - {$p->nama_pegawai}"
            ])
    )
    ->rule(function ($livewire) {
        return function (string $attribute, $value, $fail) use ($livewire) {

            $produksiId = $livewire->ownerRecord->id ?? null;

            $currentRecord = method_exists($livewire, 'getMountedTableActionRecord')
                ? $livewire->getMountedTableActionRecord()
                : null;

            if (! $produksiId) return;

            $query = \App\Models\RencanaPegawaiDempul::query()
                ->where('id_produksi_dempul', $produksiId)
                ->where('id_pegawai', $value);

            if ($currentRecord) {
                $query->where('id', '!=', $currentRecord->id);
            }

            if ($query->exists()) {
                $fail('Pegawai ini sudah ditugaskan!');
            }
        };
    })
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
