<?php

namespace App\Filament\Resources\PegawaiTurunKayus\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use App\Models\Pegawai;
use Carbon\CarbonPeriod;

class PegawaiTurunKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->options(self::timeOptions())
                    ->default('06:00') // Default: 06:00 (sore)
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null), // Tampilkan hanya HH:MM,
                Select::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->options(self::timeOptions())
                    ->default('17:00') // Default: 17:00 (sore)
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),
                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->options(
                        Pegawai::query()
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ])
                    )
                    //   ->multiple() // bisa pilih banyak
                    ->searchable()
                    ->required(),

                TextInput::make('ket')
                    ->label('Keterangan')
                    ->nullable(),
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
