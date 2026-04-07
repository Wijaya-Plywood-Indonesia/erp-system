<?php

namespace App\Filament\Resources\DetailMasuks\Schemas;

use Filament\Schemas\Schema;
use App\Models\JenisKayu;
use App\Models\Ukuran;
use App\Models\DetailHasilPaletRotary;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;

class DetailMasukForm
{
    public static function configure(Schema $schema, ?int $idProduksiDryer = null): Schema
    {
        $paletDiterima = [];

        if ($idProduksiDryer) {
            $idPaletDiterima = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                ->where('tipe', 'dryer')
                ->pluck('id_detail_hasil_palet_rotary')
                ->toArray();

            // Key pakai $d->id (unik) bukan $d->palet (bisa duplikat)
            $paletDiterima = DetailHasilPaletRotary::whereIn('id', $idPaletDiterima)
                ->with('produksi.mesin') // <-- eager load relasi mesin
                ->get()
                ->mapWithKeys(fn($d) => [
                    $d->id => "{$d->kode_palet}"
                ])
                ->toArray();
        }

        return $schema
            ->schema([
                 TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    //->required()
                    ,
                // Select::make('no_palet')
                //     ->label('Nomor Palet')
                //     ->options($paletDiterima)
                //     ->searchable()
                //     ->required()
                //     ->live()
                //     ->disabled(empty($paletDiterima))
                //     ->helperText(
                //         empty($paletDiterima)
                //             ? 'Belum ada palet yang diterima oleh produksi dryer ini.'
                //             : 'Pilih palet untuk mengisi form otomatis.'
                //     )
                //     ->afterStateUpdated(function (Set $set, ?string $state) {
                //         if (!$state) return;

                //         $palet = DetailHasilPaletRotary::with([
                //             'produksi.mesin',    // <-- tambah eager load mesin
                //             'penggunaanLahan',
                //             'setoranPaletUkuran',
                //         ])->find($state);

                //         if (!$palet) return;

                //         // Simpan kode unik SP-1 bukan angka 1
                //         $set('no_palet', $palet->kode_palet);
                //         $set('kw', $palet->kw);
                //         $set('isi', $palet->total_lembar);

                //         $idJenisKayu = $palet->penggunaanLahan?->id_jenis_kayu;
                //         if ($idJenisKayu) {
                //             $set('id_jenis_kayu', $idJenisKayu);
                //             session(['last_jenis_kayu' => $idJenisKayu]);
                //         }

                //         $ukuran = $palet->setoranPaletUkuran;
                //         if ($ukuran) {
                //             $idUkuran = DB::table('ukurans')
                //                 ->where('tebal', $ukuran->tebal)
                //                 ->where('lebar', $ukuran->lebar)
                //                 ->where('panjang', $ukuran->panjang)
                //                 ->value('id');

                //             if ($idUkuran) {
                //                 $set('id_ukuran', $idUkuran);
                //                 session(['last_ukuran' => $idUkuran]);
                //             }
                //         }
                //     })
                //     ->columnSpanFull(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id')
                    )
                    ->searchable()
                    ->afterStateUpdated(fn($state) => session(['last_jenis_kayu' => $state]))
                    ->default(fn() => session('last_jenis_kayu'))
                    ->required(),

                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->options(Ukuran::all()->pluck('dimensi', 'id'))
                    ->searchable()
                    ->afterStateUpdated(fn($state) => session(['last_ukuran' => $state]))
                    ->default(fn() => session('last_ukuran'))
                    ->required(),

                TextInput::make('kw')
                    ->label('KW (Kualitas)')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Cth: 1, 2, 3, dll.'),

                TextInput::make('isi')
                    ->label('Isi')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),
            ]);
    }
}
