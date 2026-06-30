<?php

namespace App\Filament\Resources\PegawaiDryers\Schemas;

use App\Models\Pegawai;
use App\Models\DetailPegawai; // Pastikan model DetailPegawai sudah diimport
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;

class PegawaiDryerForm
{
    public static function configure(Schema $schema, $record = null): Schema
    {
        return $schema->components([
            Select::make('id_pegawai')
                ->label('Pegawai')
                ->options(function ($livewire, $record) {
                    // Ambil ID produksi dari owner (RelationManager) atau record
                    $idProduksi = $record?->id_produksi_dryer
                        ?? $livewire->getOwnerRecord()?->id;

                    // Ambil ID pegawai yang sudah ditugaskan di produksi ini
                    $usedPegawaiIds = DetailPegawai::where('id_produksi_dryer', $idProduksi)
                        ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                        ->pluck('id_pegawai')
                        ->toArray();

                    // Hanya tampilkan pegawai yang BELUM digunakan
                    return Pegawai::query()
                        ->whereNotIn('id', $usedPegawaiIds)
                        ->get()
                        ->mapWithKeys(fn($pegawai) => [
                            $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                        ]);
                })
                ->searchable()
                ->preload()
                ->required()
                // Validasi agar tidak bisa simpan jika pegawai sudah ada (back-end check)
                ->rules([
                    fn($get, $livewire, $record) => function ($attribute, $value, $fail) use ($get, $livewire, $record) {
                        $idProduksi = $record?->id_produksi_dryer ?? $livewire->getOwnerRecord()?->id;
                        $exists = DetailPegawai::where('id_produksi_dryer', $idProduksi)
                            ->where('id_pegawai', $value)
                            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                            ->exists();

                        if ($exists) {
                            $fail('Pegawai ini sudah terdaftar dalam laporan ini.');
                        }
                    }
                ]),

            TextInput::make('tugas')
                ->label('Tugas')
                ->required()
                ->maxLength(255),

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

            TextInput::make('ijin')
                ->label('Ijin')
                ->maxLength(255),

            Textarea::make('ket')
                ->label('Keterangan')
                ->rows(1),
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
