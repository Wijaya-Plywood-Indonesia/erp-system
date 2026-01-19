<?php

namespace App\Filament\Resources\PegawaiRotaries\Schemas;

use App\Models\ProduksiRotary;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PegawaiRotaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_produksi')
                    ->label('Mesin Produksi')
                    ->relationship('produksi_rotary', 'id') // nama relasi di model + kolom yang ditampilkan
                    ->required(),

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
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null), // Tampilkan hanya HH:MM,
                //sampai sini 
                //ini akan bisa direpeater
                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->relationship('pegawai', 'nama_pegawai')
                    ->searchable()
                    ->required(),



                Select::make('role')
                    ->label('Peran Di Produksi')
                    ->options([
                        'operator_mesin' => 'Operator Mesin',
                        'petugas_pilih' => 'Petugas Pilih',
                        'operator_lain' => 'Operator Produksi Lain',
                    ])
                    ->required()
                    ->native(false),
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
