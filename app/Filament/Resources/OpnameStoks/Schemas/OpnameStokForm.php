<?php

namespace App\Filament\Resources\OpnameStoks\Schemas;

use App\Models\Ukuran;
use App\Models\JenisKayu;
use App\Models\JenisBarang;
use App\Models\Grade;
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
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

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
                    'plywood'        => 'Plywood Siap Jual',
                    'platform_jadi'  => 'Platform Jadi',
                    'triplek_jadi'   => 'Triplek Jadi',
                    'gudang_satu'    => 'Gudang Satu',
                ])
                ->required()
                ->live()
                ->columnSpanFull()
                ->afterStateUpdated(function (Get $get, Set $set) {
                    $jenisStok = $get('jenis_stok');
                    if (!$jenisStok) return;
                    $set('items', self::loadItemsFromDb($jenisStok));
                }),

            Repeater::make('items')
                ->label('Daftar Barang')
                ->columnSpanFull()
                ->addActionLabel('+ Tambah Baris Baru')
                ->grid(1) // satu kolom per baris repeater
                ->schema([

                    Select::make('id_jenis_kayu')
                        ->label('Jenis Kayu')
                        ->options(fn() => JenisKayu::pluck('nama_kayu', 'id'))
                        ->searchable()
                        ->hidden(fn(Get $get) => $get('../../jenis_stok') === 'platform_jadi')
                        ->required(fn(Get $get) => $get('../../jenis_stok') !== 'platform_jadi')
                        ->live()
                        ->afterStateUpdated(fn(Get $get, Set $set) => self::updateBaris($get, $set)),

                    Select::make('id_jenis_barang')
                        ->label('Jenis Barang')
                        ->options(fn() => JenisBarang::pluck('nama_jenis_barang', 'id'))
                        ->searchable()
                        ->hidden(fn(Get $get) => $get('../../jenis_stok') !== 'platform_jadi')
                        ->required(fn(Get $get) => $get('../../jenis_stok') === 'platform_jadi')
                        ->live()
                        ->afterStateUpdated(fn(Get $get, Set $set) => self::updateBaris($get, $set)),

                    Select::make('id_ukuran')
                        ->label('Ukuran')
                        ->options(fn() => Ukuran::all()->pluck('dimensi', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn(Get $get, Set $set) => self::updateBaris($get, $set)),

                    Select::make('kw')
                        ->label('Grade')
                        ->options(fn() => Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade'))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn(Get $get, Set $set) => self::updateBaris($get, $set)),

                    TextInput::make('stok_sistem')
                        ->label('Stok Sistem')
                        ->numeric()
                        ->readOnly()
                        ->dehydrated()
                        ->suffix('Lbr'),

                    TextInput::make('kubikasi_sistem')
                        ->label('Kbk Sistem')
                        ->numeric()
                        ->readOnly()
                        ->dehydrated()
                        ->suffix('m³'),

                    TextInput::make('stok_fisik')
                        ->label('Stok Fisik')
                        ->numeric()
                        ->suffix('Lbr'),

                    TextInput::make('kubikasi_fisik')
                        ->label('Kbk Fisik')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.0001')
                        ->suffix('m³'),

                    TextInput::make('catatan')
                        ->label('Catatan')
                        ->placeholder('Opsional'),

                ])
                ->columns(9), // semua field dalam 1 baris horizontal

        ])->columns(1);
    }

    // ... semua method loadItemsFromDb, updateBaris, bacaXxx tetap sama persis
    // (tidak ada perubahan di bagian tersebut)

    public static function loadItemsFromDb(string $jenisStok): array
    {
        return match ($jenisStok) {
            'veneer_basah'  => self::loadBasah(),
            'veneer_jadi'   => self::loadJadi(),
            'veneer_kering' => self::loadKering(),
            'platform_mth'  => self::loadPlatformMth(),
            'triplek_mth'   => self::loadTriplekMth(),
            'plywood'       => self::loadPlywood(),
            'platform_jadi' => self::loadPlatformJadi(),
            'triplek_jadi'  => self::loadTriplekJadi(),
            'gudang_satu'   => self::loadGudangSatu(),
            default         => [],
        };
    }

    private static function rowDariSummary(object $s, string $idField = 'id_jenis_kayu'): array
    {
        $ukuran = Ukuran::where([
            'panjang' => $s->panjang,
            'lebar'   => $s->lebar,
            'tebal'   => $s->tebal,
        ])->first();

        return [
            'id_jenis_kayu'   => $idField === 'id_jenis_kayu' ? $s->id_jenis_kayu : null,
            'id_jenis_barang' => $idField === 'id_jenis_barang' ? $s->id_jenis_barang : null,
            'id_ukuran'       => $ukuran?->id,
            'kw'              => $s->kw_grade ?? $s->kw ?? null,
            'stok_sistem'     => (int) $s->stok_lembar,
            'kubikasi_sistem' => round((float) $s->stok_kubikasi, 6),
            'stok_fisik'      => null,
            'kubikasi_fisik'  => null,
            'catatan'         => null,
        ];
    }

    private static function loadBasah(): array
    {
        return HppVeneerBasahSummary::all()->map(function ($s) {
            $ukuran = Ukuran::where(['panjang' => $s->panjang, 'lebar' => $s->lebar, 'tebal' => $s->tebal])->first();
            return [
                'id_jenis_kayu'   => $s->id_jenis_kayu,
                'id_jenis_barang' => null,
                'id_ukuran'       => $ukuran?->id,
                'kw'              => $s->kw,
                'stok_sistem'     => (int) $s->stok_lembar,
                'kubikasi_sistem' => round((float) $s->stok_kubikasi, 6),
                'stok_fisik'      => null,
                'kubikasi_fisik'  => null,
                'catatan'         => null,
            ];
        })->toArray();
    }

    private static function loadJadi(): array
    {
        return StokVeneerJadi::all()->map(fn($s) => self::rowDariSummary($s))->toArray();
    }

    private static function loadKering(): array
    {
        return StokVeneerKering::selectRaw('id_ukuran, id_jenis_kayu, kw')
            ->groupBy('id_ukuran', 'id_jenis_kayu', 'kw')
            ->get()
            ->map(function ($s) {
                $stokLembar = StokVeneerKering::saldoLembarTerakhir($s->id_ukuran, $s->id_jenis_kayu, $s->kw);
                $snapshot   = StokVeneerKering::snapshotTerakhir($s->id_ukuran, $s->id_jenis_kayu, $s->kw);
                return [
                    'id_jenis_kayu'   => $s->id_jenis_kayu,
                    'id_jenis_barang' => null,
                    'id_ukuran'       => $s->id_ukuran,
                    'kw'              => $s->kw,
                    'stok_sistem'     => $stokLembar,
                    'kubikasi_sistem' => round((float) $snapshot['stok_m3'], 6),
                    'stok_fisik'      => null,
                    'kubikasi_fisik'  => null,
                    'catatan'         => null,
                ];
            })->toArray();
    }

    private static function loadPlatformMth(): array
    {
        return StokPlatformMth::all()->map(fn($s) => self::rowDariSummary($s))->toArray();
    }

    private static function loadTriplekMth(): array
    {
        return StokTriplekMth::all()->map(fn($s) => self::rowDariSummary($s))->toArray();
    }

    private static function loadPlywood(): array
    {
        return StokPlywoodSiapJual::all()->map(fn($s) => self::rowDariSummary($s))->toArray();
    }

    private static function loadPlatformJadi(): array
    {
        return StokPlatformJadi::all()->map(fn($s) => self::rowDariSummary($s, 'id_jenis_barang'))->toArray();
    }

    private static function loadTriplekJadi(): array
    {
        return StokTriplekJadi::all()->map(fn($s) => self::rowDariSummary($s))->toArray();
    }

    private static function loadGudangSatu(): array
    {
        return StokGudangSatu::all()->map(fn($s) => self::rowDariSummary($s))->toArray();
    }

    public static function updateBaris(Get $get, Set $set): void
    {
        $jenisStok     = $get('../../jenis_stok');
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
            'veneer_basah'  => self::bacaBasah((int) $idEntitas, $ukuran, $kw),
            'veneer_jadi'   => self::bacaJadi((int) $idEntitas, $ukuran, $kw),
            'veneer_kering' => self::bacaKering((int) $idEntitas, (int) $idUkuran, $kw),
            'platform_mth'  => self::bacaPlatformMth((int) $idEntitas, $ukuran, $kw),
            'triplek_mth'   => self::bacaTriplekMth((int) $idEntitas, $ukuran, $kw),
            'plywood'       => self::bacaPlywood((int) $idEntitas, $ukuran, $kw),
            'platform_jadi' => self::bacaPlatformJadi((int) $idEntitas, $ukuran, $kw),
            'triplek_jadi'  => self::bacaTriplekJadi((int) $idEntitas, $ukuran, $kw),
            'gudang_satu'   => self::bacaGudangSatu((int) $idEntitas, $ukuran, $kw),
            default         => [0, 0],
        };

        $set('stok_sistem',     $stokLembar);
        $set('kubikasi_sistem', round($stokKubikasi, 6));
    }

    private static function bacaBasah(int $id, Ukuran $u, string $kw): array
    {
        $s = HppVeneerBasahSummary::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private static function bacaJadi(int $id, Ukuran $u, string $kw): array
    {
        $s = StokVeneerJadi::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private static function bacaKering(int $id, int $idUkuran, string $kw): array
    {
        $stok     = StokVeneerKering::saldoLembarTerakhir($idUkuran, $id, $kw);
        $snapshot = StokVeneerKering::snapshotTerakhir($idUkuran, $id, $kw);
        return [$stok, (float) $snapshot['stok_m3']];
    }

    private static function bacaPlatformMth(int $id, Ukuran $u, string $kw): array
    {
        $s = StokPlatformMth::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private static function bacaTriplekMth(int $id, Ukuran $u, string $kw): array
    {
        $s = StokTriplekMth::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private static function bacaPlywood(int $id, Ukuran $u, string $kw): array
    {
        $s = StokPlywoodSiapJual::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private static function bacaPlatformJadi(int $id, Ukuran $u, string $kw): array
    {
        $s = StokPlatformJadi::where(['id_jenis_barang' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private static function bacaTriplekJadi(int $id, Ukuran $u, string $kw): array
    {
        $s = StokTriplekJadi::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private static function bacaGudangSatu(int $id, Ukuran $u, string $kw): array
    {
        $s = StokGudangSatu::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }
}