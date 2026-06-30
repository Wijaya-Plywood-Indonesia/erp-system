<?php

namespace App\Filament\Resources\PegawaiPotSikus\Schemas;

use App\Models\PegawaiPotSiku;
use Filament\Schemas\Schema;
use App\Models\Pegawai;
use Filament\Forms\Components\TextInput;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\Select;

class PegawaiPotSikuForm
{
    public static function timeOptions(): array
    {
        return collect(
            CarbonPeriod::create('00:00', '1 hour', '23:00')->toArray()
        )->mapWithKeys(fn($time) => [
            $time->format('H:i') => $time->format('H.i'),
        ])->toArray();
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

                // --- JAM PULANG ---
                Select::make('pulang')
                    ->label('Jam Pulang')
                    ->options(self::timeOptions())
                    ->default('16:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                TextInput::make('tugas')
                    ->label('Tugas')
                    ->default('Pegawai Pot Siku')
                    ->readOnly(),

                // 👷 PEGAWAI (DENGAN VALIDASI DUPLIKAT)
                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->searchable()
                    ->required()
                    ->options(function ($livewire) {
                        $produksiId = $livewire->getOwnerRecord()?->id;

                        // Identifikasi record yang sedang diedit (khusus Relation Manager)
                        $currentId = $livewire->getMountedTableActionRecord()?->id;

                        if (!$produksiId) {
                            return [];
                        }

                        // Ambil ID pegawai yang sudah terdaftar di produksi ini
                        $excludeIds = PegawaiPotSiku::where('id_produksi_pot_siku', $produksiId)
                            ->when($currentId, fn($query) => $query->where('id', '!=', $currentId))
                            ->pluck('id_pegawai')
                            ->toArray();

                        // Tampilkan hanya pegawai yang belum terdaftar agar dropdown tetap rapi
                        return Pegawai::query()
                            ->whereNotIn('id', $excludeIds)
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ]);
                    })
                    ->rule(function ($livewire) {
                        return function (string $attribute, $value, $fail) use ($livewire) {
                            // Gunakan ownerRecord dari Livewire Relation Manager
                            $produksiId = $livewire->ownerRecord->id ?? null;

                            if (!$produksiId) {
                                return;
                            }

                            // ✅ KHUSUS RELATION MANAGER: Ambil ID baris yang sedang diedit
                            $currentId = $livewire->getMountedTableActionRecord()?->id;

                            $exists = PegawaiPotSiku::query()
                                ->where('id_produksi_pot_siku', $produksiId)
                                ->where('id_pegawai', $value)
                                // Jika sedang edit, abaikan pengecekan terhadap record diri sendiri
                                ->when($currentId, fn($q) => $q->where('id', '!=', $currentId))
                                ->exists();

                            if ($exists) {
                                $fail('Pegawai ini sudah terdaftar pada produksi pot siku ini.');
                            }
                        };
                    }),
            ]);
    }
}
