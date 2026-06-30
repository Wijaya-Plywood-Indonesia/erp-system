<?php

namespace App\Filament\Resources\KendaraanSupplierKayus\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class KendaraanSupplierKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('nopol_kendaraan')
                    ->label('Nomor Polisi')
                    ->required()
                    ->unique(
                        table: 'kendaraan_supplier_kayus',   // ganti sesuai nama tabel kamu
                        column: 'nopol_kendaraan',
                        ignoreRecord: true     // agar tidak error saat edit record yang sama
                    )
                    ->validationMessages([
                        'required' => 'Nomor polisi wajib diisi.',
                        'unique' => 'Nomor polisi ini sudah terdaftar, silakan periksa kembali.',
                    ]),

                Select::make('jenis_kendaraan')
                    ->label('Kategori')
                    ->options([
                        'Fuso' => 'Fuso',
                        'Truk' => 'Truk',
                        'Pick-Up' => 'Pick-Up',
                    ])
                    ->required()
                    ->native(false)
                    ->searchable(),


                TextInput::make('pemilik_kendaraan')
                    ->required(),

            ]);
    }
}
