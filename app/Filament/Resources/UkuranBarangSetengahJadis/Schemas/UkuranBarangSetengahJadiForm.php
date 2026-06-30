<?php

namespace App\Filament\Resources\UkuranBarangSetengahJadis\Schemas;

use App\Models\Grade;
use App\Models\Ukuran;
use App\Models\JenisBarang;
use App\Models\KategoriBarang;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Symfony\Contracts\Service\Attribute\Required;

class UkuranBarangSetengahJadiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->options(
                        Ukuran::all()
                            ->pluck('dimensi', 'id')
                    )
                    ->searchable()
                    ->Required(),

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
                    ->reactive()
                    ->dehydrated(false)   // ✅ TIDAK DISIMPAN KE DATABASE
                    ->afterStateUpdated(fn ($set) => $set('id_grade', null))
                    ->required(),

                Select::make('id_grade')
                    ->label('Grade')
                    ->options(function (callable $get) {

                        $idKategori = $get('kategori_barang_filter');

                        if (!$idKategori) {
                            return [];
                        }

                        return Grade::where('id_kategori_barang', $idKategori)
                            ->orderBy('nama_grade')
                            ->pluck('nama_grade', 'id');
                    })
                    ->searchable()
                    ->required(),


                TextInput::make('keterangan')
                    ->label('Keterangan')
            ]);
    }
}
