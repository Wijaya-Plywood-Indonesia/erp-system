<?php

namespace App\Filament\Resources\UkuranBarangSetengahJadis\Schemas;

use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\KategoriBarang;
use App\Models\Ukuran;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UkuranBarangSetengahJadiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->options(
                        Ukuran::all()->pluck('dimensi', 'id')
                    )
                    ->searchable()
                    ->required(),

                Select::make('id_jenis_barang')
                    ->label('Jenis Barang')
                    ->options(
                        JenisBarang::orderBy('nama_jenis_barang')
                            ->pluck('nama_jenis_barang', 'id')
                    )
                    ->searchable()
                    ->required(),

                Select::make('kategori_barang_filter')
                    ->label('Kategori Barang')
                    ->options(
                        KategoriBarang::orderBy('nama_kategori')
                            ->pluck('nama_kategori', 'id')
                    )
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (callable $set) {
                        $set('id_grade', null);
                        $set('harga', null);
                    })
                    ->afterStateHydrated(function (callable $set, $record) {
                        if ($record) {
                            $idKategori = $record->grade?->id_kategori_barang;
                            $set('kategori_barang_filter', $idKategori);
                        }
                    })
                    ->required(),

                Select::make('id_grade')
                    ->label('Grade')
                    ->options(function (callable $get) {
                        $idKategori = $get('kategori_barang_filter');

                        if (! $idKategori) {
                            return [];
                        }

                        return Grade::where('id_kategori_barang', $idKategori)
                            ->orderBy('nama_grade')
                            ->pluck('nama_grade', 'id');
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (callable $set, $state) {
                        $harga = Grade::find($state)?->harga;

                        if ($harga) {
                            $set('harga', $harga);
                        }
                    })
                    ->required(),

                TextInput::make('harga')
                    ->label('Harga')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                TextInput::make('keterangan')
                    ->label('Keterangan'),
            ]);
    }
}
