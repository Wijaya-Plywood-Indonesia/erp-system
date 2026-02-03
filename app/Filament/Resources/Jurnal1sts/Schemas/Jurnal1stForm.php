<?php

namespace App\Filament\Resources\Jurnal1sts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class Jurnal1stForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('modif10')
                    ->label('Modif 10')
                    ->options(
                        \App\Models\AnakAkun::pluck('kode_anak_akun', 'kode_anak_akun')
                    )
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Reset no akun & nama akun
                        $set('no_akun', null);
                        $set('nama_akun', null);
                    })
                    ->required(),

                Select::make('no_akun')
                    ->label('No Akun')
                    ->options(function (callable $get) {
                        $kode = $get('modif10');

                        if (!$kode)
                            return [];

                        // cari parent berdasarkan kode_anak_akun = modif10
                        $parent = \App\Models\AnakAkun::where('kode_anak_akun', $kode)->first();

                        if (!$parent)
                            return [];

                        // ambil semua children berdasarkan parent id
                        return \App\Models\AnakAkun::where('parent', $parent->id)
                            ->pluck('kode_anak_akun', 'kode_anak_akun');
                    })
                    ->preload()
                    ->reactive()
                    ->searchable()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $akun = \App\Models\AnakAkun::where('kode_anak_akun', $state)->first();
                        $set('nama_akun', $akun?->nama_anak_akun);
                    })
                    ->required(),

                TextInput::make('nama_akun')
                    ->label('Nama Akun')
                    ->disabled()
                    ->dehydrated()
                    ->required(),

                Select::make('bagian')
                    ->label('Bagian')
                    ->options([
                        'd' => 'Debit',
                        'k' => 'Kredit',
                    ])
                    ->required(),
                TextInput::make('banyak')
                    ->numeric()
                    ->nullable(),

                TextInput::make('m3')
                    ->numeric()
                    ->suffix('m³')
                    ->nullable(),

                TextInput::make('harga')
                    ->numeric()
                    ->prefix('Rp')
                    ->nullable(),

                TextInput::make('tot')
                    ->numeric()
                    ->prefix('Rp')
                    ->nullable(),

                TextInput::make('created_by')
                    ->default(fn() => auth()->user()->name)
                    ->disabled()
                    ->dehydrated() // penting! agar tetap tersimpan ke database meski disabled
            ]);
    }
}
