<?php

namespace App\Filament\Resources\DetailMasuks\Schemas;

use App\Models\DetailHasilPaletRotary;
use App\Models\DetailMasuk;
use App\Models\JenisKayu;
use App\Models\Ukuran;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class DetailMasukForm
{
    public static function configure(Schema $schema, ?int $idProduksiDryer = null): Schema
    {
        $paletDiterima = [];

        if ($idProduksiDryer) {
            $idPaletDiterima = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                ->where('tipe', 'dryer')
                ->whereNotNull('id_detail_hasil_palet_rotary')
                ->pluck('id_detail_hasil_palet_rotary')
                ->toArray();

            $paletDiterima = DetailHasilPaletRotary::whereIn('id', $idPaletDiterima)
                ->with('produksi.mesin')
                ->get()
                ->mapWithKeys(fn($d) => [
                    $d->id => $d->kode_palet
                ])
                ->toArray();
        }

        $opsiDropdown = ['AF' => '— Palet AF (Manual) —'] + $paletDiterima;

        return $schema->schema([

            Select::make('no_palet_select')
                ->label('Nomor Palet')
                ->options($opsiDropdown)
                ->searchable()
                ->required()
                ->live()
                ->disabled(empty($paletDiterima))
                ->dehydrated(false) // ✅ field UI, tidak perlu disimpan ke DB
                ->helperText(
                    empty($paletDiterima)
                        ? 'Belum ada palet yang diterima oleh produksi dryer ini.'
                        : 'Pilih palet untuk mengisi form otomatis, atau pilih AF untuk input manual.'
                )
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                    if (!$state) return;

                    if ($state === 'AF') {
                        $set('no_palet', DetailMasuk::nextAfNumber());
                        $set('kw', null);
                        $set('isi', null);
                        $set('id_ukuran', null);
                        $set('id_jenis_kayu', null);
                        return;
                    }

                    $palet = DetailHasilPaletRotary::with([
                        'produksi.mesin',
                        'penggunaanLahan',
                        'ukuran',
                    ])->find($state);

                    if (!$palet) return;

                    $set('no_palet', (int) $state);
                    $set('kw', $palet->kw);
                    $set('isi', $palet->total_lembar);

                    $idJenisKayu = $palet->penggunaanLahan?->id_jenis_kayu;
                    if ($idJenisKayu) {
                        $set('id_jenis_kayu', $idJenisKayu);
                        session(['last_jenis_kayu' => $idJenisKayu]);
                    }

                    if ($palet->id_ukuran) {
                        $set('id_ukuran', $palet->id_ukuran);
                        session(['last_ukuran' => $palet->id_ukuran]);
                    }
                })
                ->columnSpanFull(),

            Hidden::make('no_palet'),

            Placeholder::make('af_preview')
                ->label('Nomor AF yang akan digunakan')
                ->content(function (Get $get) {
                    $noPalet = $get('no_palet');
                    if ($noPalet !== null && (int) $noPalet < 0) {
                        return 'AF-' . abs((int) $noPalet);
                    }
                    return '-';
                })
                ->hidden(fn(Get $get) => $get('no_palet_select') !== 'AF')
                ->columnSpanFull(),

            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id'))
                ->searchable()
                ->afterStateUpdated(fn($state) => session(['last_jenis_kayu' => $state]))
                ->default(fn() => session('last_jenis_kayu'))
                ->disabled(
                    fn(Get $get) =>
                    $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null
                )
                ->dehydrated(true) // ✅ tetap simpan meski disabled
                ->required(),

            Select::make('id_ukuran')
                ->label('Ukuran')
                ->options(Ukuran::all()->pluck('nama_ukuran', 'id'))
                ->searchable()
                ->afterStateUpdated(fn($state) => session(['last_ukuran' => $state]))
                ->default(fn() => session('last_ukuran'))
                ->disabled(
                    fn(Get $get) =>
                    $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null
                )
                ->dehydrated(true) // ✅ tetap simpan meski disabled
                ->required(),

            TextInput::make('kw')
                ->label('KW (Kualitas)')
                ->required()
                ->maxLength(255)
                ->placeholder('Cth: 1, 2, 3, dll.')
                ->readOnly(
                    fn(Get $get) =>
                    $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null
                )
                ->dehydrated(true), // ✅ tetap simpan meski readOnly

            TextInput::make('isi')
                ->label('Isi')
                ->required()
                ->numeric()
                ->placeholder('Cth: 1.5 atau 100')
                ->readOnly(
                    fn(Get $get) =>
                    $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null
                )
                ->dehydrated(true), // ✅ tetap simpan meski readOnly
        ]);
    }
}
