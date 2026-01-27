<?php

namespace App\Filament\Resources\PegawaiGrajiStiks\Schemas;

use App\Models\PegawaiGrajiStik;
use Filament\Schemas\Schema;
use App\Models\Pegawai;
use App\Models\GrajiStik;
use Filament\Forms\Components\TextInput;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\Select;

class PegawaiGrajiStikForm
{
    // Fungsi TimeOptions
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
                // --- JAM MASUK ---
                Select::make('jam_masuk') // Sesuaikan dengan kolom fillable di model: jam_masuk
                    ->label('Jam Masuk')
                    ->options(self::timeOptions())
                    ->default('06:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                // --- JAM PULANG ---
                Select::make('jam_pulang') // Sesuaikan dengan kolom fillable di model: jam_pulang
                    ->label('Jam Pulang')
                    ->options(self::timeOptions())
                    ->default('16:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                // 👷 PEGAWAI (DENGAN VALIDASI DUPLIKAT)
                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->options(
                        Pegawai::query()
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ])
                    )
                    // ... di dalam Select::make('id_pegawai')

                    ->rule(function ($get, $livewire) {
                        return function (string $attribute, $value, $fail) use ($get, $livewire) {

                            // 1. Ambil ID Produksi (Owner Record)
                            // Di Relation Manager, ID induk biasanya ada di $livewire->ownerRecord->id
                            $produksiId = $livewire->ownerRecord->id ?? $get('id_graji_stiks');

                            if (! $produksiId) {
                                return;
                            }

                            // 2. Ambil ID Record yang sedang diedit (jika ada)
                            // Cara aman di Relation Manager adalah memeriksa model record dari livewire
                            $mountedTableActionRecord = $livewire->getMountedTableActionRecord();

                            $exists = PegawaiGrajiStik::query()
                                ->where('id_graji_stiks', $produksiId)
                                ->where('id_pegawai', $value)
                                // Jika sedang edit (ada action record), abaikan ID tersebut agar tidak dianggap duplikat
                                ->when($mountedTableActionRecord, function ($query, $recordId) {
                                    return $query->where('id', '!=', $recordId);
                                })
                                ->exists();

                            if ($exists) {
                                $fail('Pegawai ini sudah terdaftar pada produksi tanggal ini.');
                            }
                        };
                    })
                    ->searchable()
                    ->required(),
            ]);
    }
}
