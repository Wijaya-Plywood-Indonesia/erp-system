<?php

namespace App\Filament\Resources\Jurnal2s\Schemas;

use App\Models\AnakAkun;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class Jurnal2Form
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /**
                 * MODIF 100
                 * Ambil dari anak_akuns WHERE parent IS NULL
                 * Contoh: 1100 Kas, 1300 Piutang
                 */
                Select::make('modif100')
                    ->label('Modif 100')
                    ->options(
                        AnakAkun::query()
                            ->whereNull('parent')
                            ->orderBy('kode_anak_akun')
                            ->get()
                            ->mapWithKeys(fn ($item) => [
                                $item->kode_anak_akun =>
                                    $item->kode_anak_akun . ' - ' . $item->nama_anak_akun
                            ])
                            ->toArray()
                    )
                    ->searchable()
                    ->reactive()
                    ->required()
                    ->afterStateUpdated(fn (callable $set) => [
                        $set('no_akun', null),
                        $set('nama_akun', null),
                    ]),

                /**
                 * NO AKUN
                 * Ambil dari anak_akuns WHERE parent = modif100
                 * Contoh: 1110, 1120, 1130
                 */
                Select::make('no_akun')
                    ->label('No Akun')
                    ->options(function (callable $get) {
                        $kode = $get('modif100');

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

                /**
                 * NAMA AKUN (snapshot)
                 */
                TextInput::make('nama_akun')
                    ->label('Nama Akun')
                    ->disabled()
                    ->dehydrated(true)
                    ->required(),

                TextInput::make('banyak')
                    ->label('Banyak')
                    ->numeric(),

                TextInput::make('kubikasi')
                    ->label('Kubikasi')
                    ->numeric(),

                TextInput::make('harga')
                    ->label('Harga')
                    ->numeric(),

                TextInput::make('total')
                    ->label('Total')
                    ->numeric(),

                /**
                 * DIBUAT OLEH
                 */
                TextInput::make('user_id')
                    ->label('Dibuat Oleh')
                    ->default(fn () => Filament::auth()->user()?->name ?? 'Tidak diketahui')
                    ->disabled()
                    ->dehydrated(),
            ]);
    }
}
