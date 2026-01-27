<?php

namespace App\Filament\Resources\HasilGuellotines\Schemas;

use Filament\Schemas\Schema;
use App\Models\JenisKayu;
use App\Models\pegawai_guellotine;
use App\Models\Ukuran;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class HasilGuellotineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Ganti bagian Select Pegawai menjadi seperti ini:
Select::make('pegawaiGuellotines') // Sesuaikan dengan nama fungsi di model hasil_guellotine
    ->label('Pegawai')
    ->relationship('pegawaiGuellotines', 'id') // Tambahkan ini agar Filament tahu ini relasi
    ->getOptionLabelFromRecordUsing(fn($record) => $record->pegawai->nama_pegawai) // Ambil nama dari relasi pegawai
    ->required()
    ->multiple()
    ->searchable()
    ->options(function ($livewire) {
        $produksi = $livewire->getOwnerRecord();
        if (! $produksi) return [];

        return \App\Models\pegawai_guellotine::with('pegawai')
            ->where('id_produksi_guellotine', $produksi->id)
            ->get()
            ->mapWithKeys(fn($p) => [
                $p->id => $p->pegawai->nama_pegawai
            ]);
    })
    ->columnSpanFull(),
                // Relasi ke Kayu Masuk (Optional)
                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->options(
                        Ukuran::all()
                            ->pluck('dimensi', 'id') // ← memanggil accessor getDimensiAttribute()
                    )
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_ukuran' => $state]);
                    })
                    ->default(fn() => session('last_ukuran'))
                    ->required(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id')
                    )
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_jenis_kayu' => $state]);
                    })
                    ->default(fn() => session('last_jenis_kayu'))
                    ->required(),

                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),

                TextInput::make('jumlah')
                    ->label('Jumlah')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),
            ]);
    }
}
