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
                    ->options(
                        Pegawai::query()
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ])
                    )
                    ->rule(function ($livewire) {
                        return function (string $attribute, $value, $fail) use ($livewire) {

                            $produksiId = $livewire->ownerRecord->id ?? null;

                            if (!$produksiId) {
                                return;
                            }

                            // ✅ KHUSUS RELATION MANAGER
                            $currentId = $livewire->getMountedTableActionRecord()?->id;

                            $exists = PegawaiPotSiku::query()
                                ->where('id_produksi_pot_siku', $produksiId)
                                ->where('id_pegawai', $value)
                                ->when($currentId, fn($q) => $q->where('id', '!=', $currentId))
                                ->exists();

                            if ($exists) {
                                $fail('Pegawai ini sudah terdaftar pada produksi pot siku ini.');
                            }
                        };
                    })
                    ->searchable()
                    ->required(),
            ]);
    }
}
