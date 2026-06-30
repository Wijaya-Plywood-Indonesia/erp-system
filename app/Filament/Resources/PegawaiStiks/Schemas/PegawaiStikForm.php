<?php

namespace App\Filament\Resources\PegawaiStiks\Schemas;

use App\Models\DetailPegawaiStik;
use App\Models\Pegawai;
use Carbon\CarbonPeriod;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class PegawaiStikForm
{
    /**
     * @param Schema $schema
     * @param mixed $produksiStikId ID dari Produksi Stik (Parent/Owner Record)
     * @param mixed $record        Record PegawaiStik yang sedang diedit (null jika Create)
     */
    public static function configure(Schema $schema, $produksiStikId = null, $record = null): Schema
    {
        return $schema->components([
            Select::make('id_pegawai')
                ->label('Pegawai')
                ->placeholder('Cari pegawai yang belum dipilih...')
                ->searchable()
                ->required()
                ->options(function () use ($produksiStikId, $record) {
                    // 1. Ambil list ID pegawai yang sudah terdaftar di Produksi Stik ini
                    // Pastikan 'id_produksi_stik' sesuai dengan nama foreign key di tabel pegawai_stiks
                    $usedPegawaiIds = DetailPegawaiStik::query()
                        ->where('id_produksi_stik', $produksiStikId)
                        ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                        ->pluck('id_pegawai')
                        ->toArray();

                    // 2. Query pegawai yang tidak ada dalam daftar 'usedPegawaiIds'
                    return Pegawai::query()
                        ->whereNotIn('id', $usedPegawaiIds)
                        ->get()
                        ->mapWithKeys(fn($pegawai) => [
                            $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                        ]);
                })
                ->live(), // Penting agar state dropdown sinkron jika digunakan dalam Repeater

            TextInput::make('tugas')
                ->label('Tugas')
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
                ->label('Izin')
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
