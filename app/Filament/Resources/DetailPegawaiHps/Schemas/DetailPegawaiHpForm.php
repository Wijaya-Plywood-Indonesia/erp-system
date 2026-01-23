<?php

namespace App\Filament\Resources\DetailPegawaiHps\Schemas;

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
                ->options(function () {
                    return Pegawai::query()
                        // 1. Filter Nama tidak boleh '-'
                        ->where('nama_pegawai', '!=', '-')
                        // 2. Filter Nama tidak boleh string kosong
                        ->where('nama_pegawai', '!=', '')
                        // 3. Pastikan tidak null
                        ->whereNotNull('nama_pegawai')

                        ->orderBy('nama_pegawai')
                        ->get()
                        // Format tampilan: "KODE - NAMA"
                        ->mapWithKeys(fn($pegawai) => [
                            $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                        ]);
                })
                ->searchable()
                ->preload() // Bagus untuk kinerja user experience
                ->required(),
        ]);
    }
}
