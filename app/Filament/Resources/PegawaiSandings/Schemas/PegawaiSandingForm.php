<?php

namespace App\Filament\Resources\PegawaiSandings\Schemas;

use App\Models\Pegawai;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class PegawaiSandingForm
{
    public static function timeOptions(): array
    {
        // Menggunakan interval 1 jam
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



                Select::make('tugas')
                    ->label('Tugas')
                    ->options([
                        'Operator Sanding' => 'Operator Sanding',
                        'Assistant Operator Sanding' => 'Assistant Operator Sanding',
                        'Grading' => 'Grading',
                    ])

                    ->required()
                    ->native(false)
                    ->searchable(),

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
}
