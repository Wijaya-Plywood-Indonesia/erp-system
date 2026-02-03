<?php

namespace App\Filament\Resources\JurnalTigas\Schemas;

use App\Models\AnakAkun;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class JurnalTigaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. Modif1000: Menyimpan Kode Induk Akun
                Select::make('modif1000')
                    ->label('Induk Akun')
                    ->options(fn() => AnakAkun::query()
                        ->with('indukAkun')
                        ->get()
                        ->unique('id_induk_akun')
                        ->pluck('indukAkun.kode_induk_akun', 'id_induk_akun'))
                    ->live()
                    ->afterStateUpdated(fn(Set $set) => $set('akun_seratus', null)),

                // 2. Akun Seratus: Mengambil Kode Anak Akun dengan Filter Ratusan Murni
                Select::make('akun_seratus')
                    ->label('Kelompok Akun')
                    ->options(function (Get $get) {
                        $idInduk = $get('modif1000');

                        if (!$idInduk) return [];

                        return AnakAkun::where('id_induk_akun', $idInduk)
                            ->get()
                            // Logika Filter: Hanya ambil yang berakhiran "00"
                            ->filter(function ($item) {
                                // Memeriksa apakah kode berakhiran 00 (Contoh: 1100, 1200)
                                return str_ends_with((string)$item->kode_anak_akun, '00');
                            })
                            ->pluck('kode_anak_akun', 'kode_anak_akun');
                    })
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state) {
                            $nama = AnakAkun::where('kode_anak_akun', $state)->value('nama_anak_akun');
                            $set('detail', $nama);
                        }
                    })
                    ->native(false),

                // 3. Detail: Deskripsi otomatis
                TextInput::make('detail')
                    ->label('Detail Kas/Akun')
                    ->readOnly()
                    ->dehydrated(),

                // 4. Input Produksi: Banyak & Kubikasi
                TextInput::make('banyak')
                    ->numeric(),

                TextInput::make('kubikasi')
                    ->label('Kubikasi (m3)')
                    ->numeric(),

                // 5. Harga & Total: Input Manual
                TextInput::make('harga')
                    ->numeric(),

                TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->required(),

                // 6. CreatedBy: Audit trail otomatis
                TextInput::make('createdBy')
                    ->label('Petugas Input')
                    ->default(fn() => auth()->user()->name)
                    ->readOnly()
                    ->dehydrated(),
            ]);
    }
}
