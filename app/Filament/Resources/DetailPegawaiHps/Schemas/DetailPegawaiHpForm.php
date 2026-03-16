<?php

namespace App\Filament\Resources\DetailPegawaiHps\Schemas;

use App\Models\DetailPegawaiHp;
use Filament\Schemas\Schema;
use App\Models\Pegawai;
use App\Models\Mesin;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class DetailPegawaiHpForm
{
    // Fungsi helper waktu tetap sama
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
        return $schema->components([

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

            // --- TUGAS ---
            Select::make('tugas')
                ->label('Tugas')
                ->options([
                    'Operator_hp' => 'Operator HP',
                    'pilih_hp' => 'Pilih HP',
                    'nata_hp' => 'Nata HP',
                    'masak_lem' => 'Masak Lem',
                    'roll_glue' => 'Roll Glue',
                ])
                ->required()
                ->native(false)
                ->searchable(),

            // --- MESIN ---
            Select::make('id_mesin')
                ->label('Mesin Hotpress')
                ->options(
                    Mesin::whereHas('kategoriMesin', function ($query) {
                        $query->where('nama_kategori_mesin', 'HOTPRESS');
                    })
                        ->orderBy('nama_mesin')
                        ->pluck('nama_mesin', 'id')
                )
                ->searchable()
                ->required(),

            // --- PEGAWAI (MODIFIKASI DISINI) ---
            Select::make('id_pegawai')
                ->label('Pegawai')
                ->required()
                ->searchable()
                ->preload()
                ->options(function ($livewire) {
                    $produksiId = $livewire->getOwnerRecord()?->id;
                    $currentId = $livewire->getMountedTableActionRecord()?->id;

                    if (!$produksiId) return [];

                    // Ambil ID pegawai yang sudah terdaftar di sesi produksi HP ini
                    $excludeIds = DetailPegawaiHp::where('id_produksi_hp', $produksiId)
                        ->when($currentId, fn($query) => $query->where('id', '!=', $currentId))
                        ->pluck('id_pegawai')
                        ->toArray();

                    return Pegawai::query()
                        ->where('nama_pegawai', '!=', '-')
                        ->where('nama_pegawai', '!=', '')
                        ->whereNotNull('nama_pegawai')
                        ->whereNotIn('id', $excludeIds) // Hilangkan pegawai yang sudah masuk list
                        ->orderBy('nama_pegawai')
                        ->get()
                        ->mapWithKeys(fn($pegawai) => [
                            $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                        ]);
                })
                ->rule(function ($livewire) {
                    return function (string $attribute, $value, $fail) use ($livewire) {
                        $produksiId = $livewire->ownerRecord->id ?? null;
                        if (!$produksiId) return;

                        // Ambil ID record saat ini jika sedang dalam mode EDIT
                        $currentId = $livewire->getMountedTableActionRecord()?->id;

                        $exists = DetailPegawaiHp::query()
                            ->where('id_produksi_hp', $produksiId)
                            ->where('id_pegawai', $value)
                            // Jika sedang edit, jangan cek diri sendiri agar tidak dianggap duplikat
                            ->when($currentId, fn($q) => $q->where('id', '!=', $currentId))
                            ->exists();

                        if ($exists) {
                            $fail('Pegawai ini sudah terdaftar pada sesi produksi Hotpress ini.');
                        }
                    };
                }),
        ]);
    }
}
