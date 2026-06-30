<?php

namespace App\Filament\Resources\PegawaiPotJeleks\Schemas;

use App\Models\PegawaiPotJelek;
use Filament\Schemas\Schema;
use App\Models\Pegawai;
use Filament\Forms\Components\TextInput;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

class PegawaiPotJelekForm
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
                    ->default('Pegawai Pot Jelek')
                    ->readOnly(),

                // 👷 PEGAWAI (DENGAN VALIDASI DUPLIKAT)
                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->searchable()
                    ->required()
                    ->options(function ($livewire, ?Model $record) {
                        $produksiId = $livewire->getOwnerRecord()?->id;

                        // Dalam RelationManager modal, $record biasanya di-inject otomatis oleh Filament
                        // Jika null, kita coba ambil dari mounted table action record
                        $currentRecord = $record ?? (method_exists($livewire, 'getMountedTableActionRecord') ? $livewire->getMountedTableActionRecord() : null);

                        if (!$produksiId) {
                            return [];
                        }

                        // Ambil ID pegawai yang sudah terdaftar di produksi ini
                        $excludeIds = PegawaiPotJelek::where('id_produksi_pot_jelek', $produksiId)
                            ->when($currentRecord, function ($query, $currentRecord) {
                                // Jangan exclude pegawai yang sedang kita edit sekarang
                                return $query->where('id', '!=', $currentRecord->id);
                            })
                            ->pluck('id_pegawai')
                            ->toArray();

                        // Tampilkan hanya pegawai yang belum terdaftar (atau sedang diedit)
                        return Pegawai::query()
                            ->whereNotIn('id', $excludeIds)
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ]);
                    })
                    ->rule(function ($livewire, ?Model $record) {
                        return function (string $attribute, $value, $fail) use ($livewire, $record) {
                            $produksiId = $livewire->getOwnerRecord()?->id;

                            // Identifikasi record yang sedang diedit
                            $currentRecord = $record ?? (method_exists($livewire, 'getMountedTableActionRecord') ? $livewire->getMountedTableActionRecord() : null);

                            if (!$produksiId) return;

                            // Cek apakah pegawai sudah terdaftar di produksi yang sama
                            $exists = PegawaiPotJelek::query()
                                ->where('id_produksi_pot_jelek', $produksiId)
                                ->where('id_pegawai', $value)
                                ->when($currentRecord, function ($query, $currentRecord) {
                                    // Jika sedang edit, abaikan pengecekan terhadap record diri sendiri
                                    return $query->where('id', '!=', $currentRecord->id);
                                })
                                ->exists();

                            if ($exists) {
                                $fail('Pegawai ini sudah terdaftar pada produksi ini.');
                            }
                        };
                    }),
            ]);
    }
}
