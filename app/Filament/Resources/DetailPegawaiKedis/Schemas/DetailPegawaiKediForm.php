<?php

namespace App\Filament\Resources\DetailPegawaiKedis\Schemas;

use Filament\Schemas\Schema;
use App\Models\Pegawai;
use App\Models\Mesin;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class DetailPegawaiKediForm
{
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
                    // Menyimpan ke DB sebagai 'HH:MM:00'
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    // Menampilkan di form hanya 'HH:MM'
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                // --- JAM PULANG (Select dengan Options khusus) ---
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
                    ->default('Pegawai Bongkar')
                    ->readOnly(),

                Select::make('id_mesin')
                    ->label('Ruang Kedi')
                    ->options(
                        Mesin::whereHas('kategoriMesin', function ($query) {
                            $query->where('nama_kategori_mesin', 'DRYER');
                        })
                            ->orderBy('nama_mesin')
                            ->pluck('nama_mesin', 'id')
                    )
                    ->searchable()
                    ->required(),

                // --- ID PEGAWAI (Relation: pegawai) ---
                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->options(
                        Pegawai::query()
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ])
                    )
                    ->searchable()
                    ->required(),
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
