<?php

namespace App\Filament\Resources\DetailBarangDikerjakanPotSikus\Schemas;

use App\Models\PegawaiPotSiku;
use App\Models\Target;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use App\Models\JenisKayu;
use App\Models\Ukuran;
use Filament\Forms\Components\TextInput;

class DetailBarangDikerjakanPotSikuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_pegawai_pot_siku')
                    ->label('Pegawai')
                    ->required()
                    ->searchable()
                    ->options(function ($livewire) {

                        // 🔑 Ambil Produksi Nyusup (parent)
                        $produksi = $livewire->getOwnerRecord();

                        if (!$produksi) {
                            return [];
                        }

                        return PegawaiPotSiku::with('pegawai')
                            ->where('id_produksi_pot_siku', $produksi->id)
                            ->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => $p->pegawai->nama_pegawai
                            ]);
                    })
                    ->columnSpanFull(),
                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->searchable()
                    ->options(
                        Ukuran::whereIn(
                            'id',
                            Target::where('id_mesin', 16)->pluck('id_ukuran')
                        )
                            ->get()
                            ->pluck('dimensi', 'id')
                    )
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

                TextInput::make('tinggi')
                    ->label('Tinggi')
                    ->numeric()
                    ->suffix('cm')
                    ->required(),

                TextInput::make('kw')
                    ->label('KW (Kualitas)')
                    ->default('AF')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Cth: 1, 2, 3, af, dll.'),

                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),

            ]);
    }
}
