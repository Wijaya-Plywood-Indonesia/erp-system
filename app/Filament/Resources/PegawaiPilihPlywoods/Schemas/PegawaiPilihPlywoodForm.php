<?php

namespace App\Filament\Resources\PegawaiPilihPlywoods\Schemas;

use App\Models\PegawaiPilihPlywood;
use Filament\Schemas\Schema;
use Carbon\CarbonPeriod;
use App\Models\Pegawai;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class PegawaiPilihPlywoodForm
{
    public static function timeOptions(): array
    {
        // Menggunakan interval 1 jam
        return collect(CarbonPeriod::create('00:00', '1 hour', '23:00')->toArray())
            ->mapWithKeys(fn($time) => [
                $time->format('H:i') => $time->format('H.i'),
            ])
            ->toArray();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('masuk')
                    ->label('Jam Masuk')
                    ->options(self::timeOptions())
                    ->default('06:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                Select::make('pulang')
                    ->label('Jam Pulang')
                    ->options(self::timeOptions())
                    ->default('17:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                TextInput::make('tugas')
                    ->label('Tugas')
                    ->default('Pegawai Pilih Plywood')
                    ->readOnly(),

                // --- ID PEGAWAI (AUTO HIDE) ---
                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->options(function ($livewire) {
                        // 1. Ambil ID Produksi Parent
                        $produksiId = $livewire->ownerRecord->id ?? null;

                        // 2. Ambil ID Record yang sedang diedit (jika ada)
                        // Agar saat edit, pegawai yang sedang dipilih tidak hilang dari list
                        $currentRecordId = null;
                        if (method_exists($livewire, 'getMountedTableActionRecord')) {
                            $currentRecordId = $livewire->getMountedTableActionRecord()?->id;
                        }

                        // 3. Ambil daftar pegawai yang SUDAH ADA di produksi ini
                        $usedPegawaiIds = [];
                        if ($produksiId) {
                            $usedPegawaiIds = PegawaiPilihPlywood::query()
                                ->where('id_produksi_pilih_plywood', $produksiId)
                                ->when($currentRecordId, fn($q) => $q->where('id', '!=', $currentRecordId)) // Kecualikan diri sendiri saat edit
                                ->pluck('id_pegawai')
                                ->toArray();
                        }

                        // 4. Return pegawai yang BELUM terdaftar
                        return Pegawai::query()
                            ->whereNotIn('id', $usedPegawaiIds) // Filter exclude
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ]);
                    })
                    ->searchable()
                    ->required()
                    ->rule(function ($livewire) {
                        return function (string $attribute, $value, $fail) use ($livewire) {
                            // Validasi backend (double check)
                            $produksiId = $livewire->ownerRecord->id ?? null;
                            $currentRecordId = null;

                            if (method_exists($livewire, 'getMountedTableActionRecord')) {
                                $currentRecordId = $livewire->getMountedTableActionRecord()?->id;
                            }

                            if (! $produksiId) return;

                            $exists = PegawaiPilihPlywood::query()
                                ->where('id_produksi_pilih_plywood', $produksiId)
                                ->where('id_pegawai', $value)
                                ->when($currentRecordId, fn($q) => $q->where('id', '!=', $currentRecordId))
                                ->exists();

                            if ($exists) {
                                $fail('Pegawai ini sudah terdaftar.');
                            }
                        };
                    }),
            ]);
    }
}
