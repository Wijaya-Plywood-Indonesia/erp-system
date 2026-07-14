<?php

namespace App\Filament\Resources\OpnameStoks\Schemas;

use App\Models\Ukuran;
use App\Models\JenisKayu;
use App\Models\JenisBarang;
use App\Models\HppVeneerBasahSummary;
use App\Models\StokVeneerJadi;
use App\Models\StokVeneerKering;
use App\Models\StokPlatformMth;
use App\Models\StokTriplekMth;
use App\Models\StokPlywoodSiapJual;
use App\Models\StokPlatformJadi;
use App\Models\StokTriplekJadi;
use App\Models\StokGudangSatu;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class OpnameStokForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('jenis_stok')
                ->label('Jenis Stok')
                ->options([
                    'veneer_basah'   => 'Veneer Basah',
                    'veneer_kering'  => 'Veneer Kering',
                    'veneer_jadi'    => 'Veneer Jadi',
                    'platform_mth'   => 'Platform MTH',
                    'triplek_mth'    => 'Triplek MTH',
                    'platform_jadi'  => 'Platform Jadi',
                    'triplek_jadi'   => 'Triplek Jadi',
                    'gudang_satu'    => 'Gudang Satu',
                    'plywood'        => 'Plywood Siap Jual',
                ])
                ->default('veneer_basah')
                ->required()
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set) {
                    $set('stok_sistem', 0);
                    $set('kubikasi_sistem', 0);
                    $set('id_jenis_kayu', null);
                    $set('id_jenis_barang', null);
                    self::updateStokInfo($get, $set);
                }),

            // Muncul untuk semua KECUALI platform_jadi
            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(fn() => JenisKayu::pluck('nama_kayu', 'id'))
                ->required(fn(Get $get) => $get('jenis_stok') !== 'platform_jadi')
                ->hidden(fn(Get $get) => $get('jenis_stok') === 'platform_jadi')
                ->searchable()
                ->live()
                ->afterStateUpdated(fn(Get $get, Set $set) => self::updateStokInfo($get, $set)),

            // HANYA muncul untuk platform_jadi
            Select::make('id_jenis_barang')
                ->label('Jenis Barang')
                ->options(fn() => JenisBarang::pluck('nama_jenis_barang', 'id'))
                ->required(fn(Get $get) => $get('jenis_stok') === 'platform_jadi')
                ->hidden(fn(Get $get) => $get('jenis_stok') !== 'platform_jadi')
                ->searchable()
                ->live()
                ->afterStateUpdated(fn(Get $get, Set $set) => self::updateStokInfo($get, $set)),

            Select::make('kw')
                ->label('KW / Grade')
                ->options(['1' => '1', '2' => '2', '3' => '3', '4' => '4'])
                ->required()
                ->live()
                ->afterStateUpdated(fn(Get $get, Set $set) => self::updateStokInfo($get, $set)),

            Select::make('id_ukuran')
                ->label('Ukuran Barang (P x L x T)')
                ->options(fn() => Ukuran::all()->pluck('dimensi', 'id'))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn(Get $get, Set $set) => self::updateStokInfo($get, $set)),

            TextInput::make('stok_sistem')
                ->label('Stok Sistem')
                ->numeric()
                ->readOnly()
                ->dehydrated()
                ->suffix('Lembar'),

            TextInput::make('stok_fisik')
                ->label('Stok Fisik')
                ->numeric()
                ->required()
                ->suffix('Lembar'),

            TextInput::make('kubikasi_sistem')
                ->label('Kubikasi Sistem')
                ->numeric()
                ->readOnly()
                ->dehydrated()
                ->suffix('m³'),

            TextInput::make('kubikasi_fisik')
                ->label('Kubikasi Fisik')
                ->helperText('Pakai titik untuk desimal, contoh: 1.9883')
                ->required()
                ->numeric()
                ->minValue(0)
                ->step('0.0001')
                ->suffix('m³'),

            Textarea::make('catatan')
                ->label('Catatan')
                ->columnSpanFull(),

        ])->columns(2);
    }

    private static function updateStokInfo(Get $get, Set $set): void
    {
        $jenisStok     = $get('jenis_stok');
        $idUkuran      = $get('id_ukuran');
        $idJenisKayu   = $get('id_jenis_kayu');
        $idJenisBarang = $get('id_jenis_barang');
        $kw            = $get('kw');

        $idEntitas = $jenisStok === 'platform_jadi' ? $idJenisBarang : $idJenisKayu;

        if (!$jenisStok || !$idUkuran || !$idEntitas || !$kw) {
            $set('stok_sistem',     0);
            $set('kubikasi_sistem', 0);
            return;
        }

        $ukuran = Ukuran::find($idUkuran);
        if (!$ukuran) return;

        [$stokLembar, $stokKubikasi] = match ($jenisStok) {
            'veneer_basah'  => self::bacaStokBasah((int) $idEntitas, $ukuran, (string) $kw),
            'veneer_jadi'   => self::bacaStokJadi((int) $idEntitas, $ukuran, (string) $kw),
            'veneer_kering' => self::bacaStokKering((int) $idEntitas, (int) $idUkuran, (string) $kw),
            'platform_mth'  => self::bacaStokPlatformMth((int) $idEntitas, $ukuran, (string) $kw),
            'triplek_mth'   => self::bacaStokTriplekMth((int) $idEntitas, $ukuran, (string) $kw),
            'plywood'       => self::bacaStokPlywood((int) $idEntitas, $ukuran, (string) $kw),
            'platform_jadi' => self::bacaStokPlatformJadi((int) $idEntitas, $ukuran, (string) $kw),
            'triplek_jadi'  => self::bacaStokTriplekJadi((int) $idEntitas, $ukuran, (string) $kw),
            'gudang_satu'   => self::bacaStokGudangSatu((int) $idEntitas, $ukuran, (string) $kw),
            default         => [0, 0],
        };

        $set('stok_sistem',     $stokLembar);
        $set('kubikasi_sistem', round($stokKubikasi, 6));
    }

    private static function bacaStokBasah(int $idJenisKayu, Ukuran $ukuran, string $kw): array
    {
        $summary = HppVeneerBasahSummary::where([
            'id_jenis_kayu' => $idJenisKayu,
            'panjang'       => $ukuran->panjang,
            'lebar'         => $ukuran->lebar,
            'tebal'         => $ukuran->tebal,
            'kw'            => $kw,
        ])->first();
        return [
            $summary ? (int) $summary->stok_lembar : 0,
            $summary ? (float) $summary->stok_kubikasi : 0.0,
        ];
    }

    private static function bacaStokJadi(int $idJenisKayu, Ukuran $ukuran, string $kw): array
    {
        $summary = StokVeneerJadi::where([
            'id_jenis_kayu' => $idJenisKayu,
            'panjang'       => $ukuran->panjang,
            'lebar'         => $ukuran->lebar,
            'tebal'         => $ukuran->tebal,
            'kw_grade'      => $kw,
        ])->first();
        return [
            $summary ? (int) $summary->stok_lembar : 0,
            $summary ? (float) $summary->stok_kubikasi : 0.0,
        ];
    }

    private static function bacaStokKering(int $idJenisKayu, int $idUkuran, string $kw): array
    {
        $stokLembar = StokVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);
        $snapshot   = StokVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kw);
        return [$stokLembar, (float) $snapshot['stok_m3']];
    }

    private static function bacaStokPlatformMth(int $idJenisKayu, Ukuran $ukuran, string $kw): array
    {
        $summary = StokPlatformMth::where([
            'id_jenis_kayu' => $idJenisKayu,
            'panjang'       => $ukuran->panjang,
            'lebar'         => $ukuran->lebar,
            'tebal'         => $ukuran->tebal,
            'kw_grade'      => $kw,
        ])->first();
        return [
            $summary ? (int) $summary->stok_lembar : 0,
            $summary ? (float) $summary->stok_kubikasi : 0.0,
        ];
    }

    private static function bacaStokTriplekMth(int $idJenisKayu, Ukuran $ukuran, string $kw): array
    {
        $summary = StokTriplekMth::where([
            'id_jenis_kayu' => $idJenisKayu,
            'panjang'       => $ukuran->panjang,
            'lebar'         => $ukuran->lebar,
            'tebal'         => $ukuran->tebal,
            'kw_grade'      => $kw,
        ])->first();
        return [
            $summary ? (int) $summary->stok_lembar : 0,
            $summary ? (float) $summary->stok_kubikasi : 0.0,
        ];
    }

    private static function bacaStokPlywood(int $idJenisKayu, Ukuran $ukuran, string $kw): array
    {
        $summary = StokPlywoodSiapJual::where([
            'id_jenis_kayu' => $idJenisKayu,
            'panjang'       => $ukuran->panjang,
            'lebar'         => $ukuran->lebar,
            'tebal'         => $ukuran->tebal,
            'kw_grade'      => $kw,
        ])->first();
        return [
            $summary ? (int) $summary->stok_lembar : 0,
            $summary ? (float) $summary->stok_kubikasi : 0.0,
        ];
    }

    private static function bacaStokPlatformJadi(int $idJenisBarang, Ukuran $ukuran, string $kw): array
    {
        $summary = StokPlatformJadi::where([
            'id_jenis_barang' => $idJenisBarang,
            'panjang'         => $ukuran->panjang,
            'lebar'           => $ukuran->lebar,
            'tebal'           => $ukuran->tebal,
            'kw_grade'        => $kw,
        ])->first();
        return [
            $summary ? (int) $summary->stok_lembar : 0,
            $summary ? (float) $summary->stok_kubikasi : 0.0,
        ];
    }

    private static function bacaStokTriplekJadi(int $idJenisKayu, Ukuran $ukuran, string $kw): array
    {
        $summary = StokTriplekJadi::where([
            'id_jenis_kayu' => $idJenisKayu,
            'panjang'       => $ukuran->panjang,
            'lebar'         => $ukuran->lebar,
            'tebal'         => $ukuran->tebal,
            'kw_grade'      => $kw,
        ])->first();
        return [
            $summary ? (int) $summary->stok_lembar : 0,
            $summary ? (float) $summary->stok_kubikasi : 0.0,
        ];
    }

    private static function bacaStokGudangSatu(int $idJenisKayu, Ukuran $ukuran, string $kw): array
    {
        $summary = StokGudangSatu::where([
            'id_jenis_kayu' => $idJenisKayu,
            'panjang'       => $ukuran->panjang,
            'lebar'         => $ukuran->lebar,
            'tebal'         => $ukuran->tebal,
            'kw_grade'      => $kw,
        ])->first();
        return [
            $summary ? (int) $summary->stok_lembar : 0,
            $summary ? (float) $summary->stok_kubikasi : 0.0,
        ];
    }
}
