<?php

namespace App\Filament\Resources\Neracas\Schemas;

use App\Models\IndukAkun;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class NeracaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. Akun Seribu: Mengambil data langsung dari Master Induk Akun
                Select::make('akun_seribu')
                    ->label('Akun Seribu')
                    ->options(fn() => IndukAkun::pluck('kode_induk_akun', 'kode_induk_akun'))
                    ->live() // Memungkinkan reaktivitas saat nilai berubah
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state) {
                            // Mencari nama_induk_akun berdasarkan kode yang dipilih secara manual
                            $namaAkun = IndukAkun::where('kode_induk_akun', $state)->value('nama_induk_akun');

                            // Mengisi kolom detail secara otomatis untuk mencegah manipulasi
                            $set('detail', $namaAkun);
                        } else {
                            $set('detail', null);
                        }
                    })
                    ->required(),

                // 2. Detail: Read-only agar konsisten dengan master data akun
                TextInput::make('detail')
                    ->label('Keterangan Seribu')
                    ->readOnly()
                    ->dehydrated(),

                // 3. Input Produksi & Keuangan
                TextInput::make('banyak')
                    ->label('Banyak')
                    ->numeric(),

                TextInput::make('kubikasi')
                    ->label('M3')
                    ->numeric(),

                TextInput::make('harga')
                    ->label('Harga')
                    ->numeric(),

                // 4. Total: Input manual sesuai permintaan Anda
                TextInput::make('total')
                    ->label('Total')
                    ->numeric(),
            ]);
    }
}
