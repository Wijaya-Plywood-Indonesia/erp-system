<?php

namespace App\Filament\Resources\LainLains\Schemas;

use App\Models\LainLain;
use App\Models\Pegawai;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class LainLainForm
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

                // --- ID PEGAWAI (Relation: pegawai) ---
                Select::make('id_pegawai')
    ->label('Pegawai')
    ->required()
    ->searchable()
    ->preload()
    ->options(function ($livewire) {
        $detailId = $livewire->getOwnerRecord()?->id;
        $currentId = $livewire->getMountedTableActionRecord()?->id;

        if (!$detailId) return [];

        // Ambil pegawai yang sudah dipakai di detail lain-lain ini
        $excludeIds = LainLain::where('id_detail_lain_lain', $detailId)
            ->when($currentId, fn($q) => $q->where('id', '!=', $currentId))
            ->pluck('id_pegawai')
            ->toArray();

        return Pegawai::query()
            ->where('nama_pegawai', '!=', '-')
            ->where('nama_pegawai', '!=', '')
            ->whereNotNull('nama_pegawai')
            ->whereNotIn('id', $excludeIds)
            ->orderBy('nama_pegawai')
            ->get()
            ->mapWithKeys(fn($pegawai) => [
                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
            ]);
    })
    ->rule(function ($livewire) {
        return function (string $attribute, $value, $fail) use ($livewire) {
            $detailId = $livewire->getOwnerRecord()?->id;
            if (!$detailId) return;

            $currentId = $livewire->getMountedTableActionRecord()?->id;

            $exists = LainLain::query()
                ->where('id_detail_lain_lain', $detailId)
                ->where('id_pegawai', $value)
                ->when($currentId, fn($q) => $q->where('id', '!=', $currentId))
                ->exists();

            if ($exists) {
                $fail('Pegawai ini sudah terdaftar pada data lain-lain ini.');
            }
        };
    }),

                TextInput::make('ijin')
                    ->label('Ijin')
                    ->maxLength(255),

                Textarea::make('ket')
                    ->label('Keterangan')
                    ->maxLength(255),

                Textarea::make('hasil')
                    ->label('Hasil')
                    ->maxLength(255),
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
