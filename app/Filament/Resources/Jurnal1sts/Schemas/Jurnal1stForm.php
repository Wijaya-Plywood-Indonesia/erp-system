<?php

namespace App\Filament\Resources\Jurnal1sts\Schemas;

use App\Models\AnakAkun;
use App\Models\SubAnakAkun;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class Jurnal1stForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /**
                 * MODIF 10
                 * Bisa pilih ratusan (1200, 1300)
                 * atau puluhan (1110, 1120)
                 */
                Select::make('modif10')
                    ->label('Modif 10')
                    ->options(
                        AnakAkun::orderBy('kode_anak_akun')
                            ->get()
                            ->mapWithKeys(fn($item) => [
                                $item->kode_anak_akun =>
                                $item->kode_anak_akun . ' - ' . $item->nama_anak_akun
                            ])
                            ->toArray()
                    )
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function (callable $set) {
                        $set('no_akun', null);
                        $set('nama_akun', null);
                    })
                    ->required(),

                /**
                 * NO AKUN
                 * Selalu SUB AKUN dari modif10
                 */
                Select::make('no_akun')
                    ->label('No Akun')
                    ->options(function (callable $get) {
                        $kode = $get('modif10');
                        if (!$kode) return [];

                        $anakAkun = AnakAkun::where('kode_anak_akun', $kode)->first();
                        if (!$anakAkun) return [];

                        return SubAnakAkun::where('id_anak_akun', $anakAkun->id)
                            ->orderBy('kode_sub_anak_akun')
                            ->get()
                            ->mapWithKeys(fn($sub) => [
                                // Pastikan key tetap string agar koma tidak hilang
                                (string) $sub->kode_sub_anak_akun => $sub->kode_sub_anak_akun . ' - ' . $sub->nama_sub_anak_akun
                            ])
                            ->toArray();
                    })
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Cari menggunakan string
                        $sub = SubAnakAkun::where('kode_sub_anak_akun', (string) $state)->first();
                        $set('nama_akun', $sub?->nama_sub_anak_akun);
                    })
                    ->required(),

                /**
                 * NAMA AKUN (snapshot)
                 */
                TextInput::make('nama_akun')
                    ->label('Nama Akun')
                    ->disabled()
                    ->dehydrated()
                    ->formatStateUsing(fn($state) => (string) $state)
                    ->required(),

                Select::make('bagian')
                    ->label('Bagian')
                    ->options([
                        'd' => 'Debit',
                        'k' => 'Kredit',
                    ])
                    ->required(),

                TextInput::make('banyak')->numeric()->nullable(),

                TextInput::make('m3')
                    ->numeric()
                    ->suffix('m³')
                    ->nullable(),

                TextInput::make('harga')
                    ->numeric()
                    ->prefix('Rp')
                    ->nullable(),

                TextInput::make('total')
                    ->numeric()
                    ->prefix('Rp')
                    ->nullable(),

                TextInput::make('created_by')
                    ->default(fn() => auth()->user()->name)
                    ->disabled()
                    ->dehydrated(),
            ]);
    }
}
