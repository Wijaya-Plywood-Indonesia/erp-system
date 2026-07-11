<?php

namespace App\Filament\Resources\PegawaiTerimaGudangSatus\Schemas;

use App\Models\PegawaiTerimaGudangSatu;
use Filament\Schemas\Schema;
use Carbon\CarbonPeriod;
use App\Models\Pegawai;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class PegawaiTerimaGudangSatuForm
{
    public static function timeOptions(): array
    {
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
                    ->default('16:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                TextInput::make('tugas')
                    ->label('Tugas')
                    ->default('Pegawai Terima Gudang Satu')
                    ->readOnly(),

                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->options(function ($livewire) {
                        $produksiId = $livewire->ownerRecord->id ?? null;
                        $currentRecordId = null;
                        if (method_exists($livewire, 'getMountedTableActionRecord')) {
                            $currentRecordId = $livewire->getMountedTableActionRecord()?->id;
                        }

                        $usedPegawaiIds = [];
                        if ($produksiId) {
                            $usedPegawaiIds = PegawaiTerimaGudangSatu::query()
                                ->where('id_produksi_terima_gudang_satu', $produksiId)
                                ->when($currentRecordId, fn($q) => $q->where('id', '!=', $currentRecordId))
                                ->pluck('id_pegawai')
                                ->toArray();
                        }

                        return Pegawai::query()
                            ->whereNotIn('id', $usedPegawaiIds)
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ]);
                    })
                    ->searchable()
                    ->required()
                    ->rule(function ($livewire) {
                        return function (string $attribute, $value, $fail) use ($livewire) {
                            $produksiId = $livewire->ownerRecord->id ?? null;
                            $currentRecordId = null;

                            if (method_exists($livewire, 'getMountedTableActionRecord')) {
                                $currentRecordId = $livewire->getMountedTableActionRecord()?->id;
                            }

                            if (! $produksiId) return;

                            $exists = PegawaiTerimaGudangSatu::query()
                                ->where('id_produksi_terima_gudang_satu', $produksiId)
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
