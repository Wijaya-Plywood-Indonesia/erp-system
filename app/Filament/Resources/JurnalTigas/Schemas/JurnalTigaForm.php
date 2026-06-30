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
                Select::make('modif1000')
                    ->label('Induk Akun')
                    ->options(fn() => AnakAkun::query()
                        ->with('indukAkun')
                        ->get()
                        ->unique('id_induk_akun')
                        // PARAMETER KEDUA diubah menjadi kode_induk_akun agar database menyimpan 1000, bukan ID
                        ->pluck('indukAkun.kode_induk_akun', 'indukAkun.kode_induk_akun'))
                    ->live()
                    ->native(false)
                    ->afterStateUpdated(fn(Set $set) => $set('akun_seratus', null)),

                // 2. Akun Seratus: Mencari berdasarkan KODE induk
                Select::make('akun_seratus')
                    ->label('Anak Akun')
                    ->options(function (Get $get) {
                        $kodeInduk = $get('modif1000'); // Sekarang berisi kode (misal: 1000)

                        if (!$kodeInduk) return [];

                        // Karena modif1000 mengirim KODE, kita cari AnakAkun yang punya IndukAkun dengan kode tersebut
                        return AnakAkun::whereHas('indukAkun', function ($query) use ($kodeInduk) {
                            $query->where('kode_induk_akun', $kodeInduk);
                        })
                            ->get()
                            ->filter(function ($item) {
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

                TextInput::make('status')
                    ->label('Status')
                    ->default('Belum Sinkron')
                    ->readOnly()
                    ->dehydrated(),
            ]);
    }
}
