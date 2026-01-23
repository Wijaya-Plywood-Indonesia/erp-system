<?php

namespace App\Filament\Resources\PegawaiSandingJoints\Schemas;

use Filament\Schemas\Schema;
use App\Models\Pegawai;
use Filament\Forms\Components\TextInput;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\Select;
use App\Models\PegawaiSandingJoint;

class PegawaiSandingJointForm
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
                // --- JAM MASUK ---
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
                    ->default('Pegawai Sanding Joint')
                    ->readOnly(),

                // 👷 PEGAWAI (DENGAN VALIDASI DUPLIKAT)
                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->searchable()
                    ->required()
                    ->options(
                        Pegawai::query()
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ])
                    )
                    ->rule(function ($livewire, $component) { // Tambahkan $component di sini
                        return function (string $attribute, $value, $fail) use ($livewire, $component) {
                            $produksiId = $livewire->ownerRecord->id ?? null;

                            if (! $produksiId) {
                                return;
                            }

                            // Ambil ID record saat ini (akan null jika sedang 'Create')
                            $currentRecordId = $component->getRecord()?->id;

                            $query = PegawaiSandingJoint::query()
                                ->where('id_produksi_sanding_joint', $produksiId)
                                ->where('id_pegawai', $value);

                            // JIKA SEDANG EDIT: Jangan cek diri sendiri
                            if ($currentRecordId) {
                                $query->where('id', '!=', $currentRecordId);
                            }

                            $exists = $query->exists();

                            if ($exists) {
                                $fail('Pegawai ini sudah terdaftar pada produksi sanding joint ini.');
                            }
                        };
                    }),
            ]);
    }
}
