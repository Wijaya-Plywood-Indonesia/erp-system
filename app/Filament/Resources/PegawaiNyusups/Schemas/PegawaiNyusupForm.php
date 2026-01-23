<?php

namespace App\Filament\Resources\PegawaiNyusups\Schemas;

use Filament\Schemas\Schema;
use Carbon\CarbonPeriod;
use App\Models\Pegawai;
use App\Models\PegawaiNyusup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class PegawaiNyusupForm
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

                TextInput::make('tugas')
                    ->label('Tugas')
                    ->default('Pegawai Nyusup')
                    ->readOnly(),

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
                    ->rule(function ($livewire) {
                        return function (string $attribute, $value, $fail) use ($livewire) {

                            $produksiId = $livewire->ownerRecord->id ?? null;

                            if (! $produksiId) {
                                return;
                            }

                            $exists = PegawaiNyusup::query()
                                ->where('id_produksi_nyusup', $produksiId)
                                ->where('id_pegawai', $value)
                                ->exists();

                            if ($exists) {
                                $fail('Pegawai ini sudah terdaftar pada produksi nyusup ini.');
                            }
                        };
                    })
                    ->searchable()
                    ->required(),
            ]);
    }
}
