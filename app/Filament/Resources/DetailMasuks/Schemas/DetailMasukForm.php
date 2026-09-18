<?php

namespace App\Filament\Resources\DetailMasuks\Schemas;

use App\Models\DetailMasuk;
use App\Models\DetailMasukStik;
use App\Models\JenisKayu;
use App\Models\SerahTerimaVeneerBasah;
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
    public static function configure(
        Schema $schema,
        ?int $idProduksi = null,
        string $tipe = 'dryer'
    ): Schema {
        // 'stik' sekarang manual sepenuhnya (lihat configureStikLegacy) —
        // tidak lagi tergantung pivot serah terima Rotary.
        if ($tipe === 'stik') {
            return static::configureStikLegacy($schema, $idProduksi);
        }

        $foreignKey = 'id_produksi_dryer';

        return $schema->schema([
            Hidden::make($foreignKey)
                ->default($idProduksi)
                ->required()
                ->dehydrated(true),

            Select::make('id_serah_terima_veneer_basah')
                ->label('Veneer Basah Diterima (dari Gudang)')
                ->helperText('Hanya menampilkan veneer basah yang sudah dikonfirmasi "Terima" dari Gudang dan masih ada sisa yang belum dipakai.')
                ->options(function ($record) {
                    $sudahDipakai = DB::table('detail_masuks')
                        ->whereNotNull('id_serah_terima_veneer_basah')
                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                        ->selectRaw('id_serah_terima_veneer_basah, SUM(isi) as total_dipakai')
                        ->groupBy('id_serah_terima_veneer_basah')
                        ->pluck('total_dipakai', 'id_serah_terima_veneer_basah');

                    $rows = SerahTerimaVeneerBasah::with(['detail.ukuran', 'detail.jenisKayu'])
                        ->where('tujuan', 'dryer')
                        ->where('status', 'Diterima')
                        ->get();

                    $options = [];
                    foreach ($rows as $row) {
                        $d = $row->detail;
                        if (! $d) {
                            continue;
                        }
                        $dipakai = (int) ($sudahDipakai[$row->id] ?? 0);
                        $sisa = (int) $d->qty_lembar - $dipakai;
                        if ($sisa <= 0) {
                            continue;
                        }
                        $ukuran = $d->ukuran ? "{$d->ukuran->panjang}x{$d->ukuran->lebar}x{$d->ukuran->tebal}" : '-';
                        $kayu = $d->jenisKayu?->nama_kayu ?? '-';
                        $options[$row->id] = "{$kayu} | {$ukuran} | KW {$d->kw} | sisa {$sisa} lbr";
                    }

                    return $options;
                })
                ->searchable()
                ->required(fn ($record) => $record === null)
                ->live()
                ->disabled(fn ($record) => $record !== null)
                ->afterStateUpdated(function (Set $set, ?string $state) {
                    if (! $state) {
                        return;
                    }

                    $row = SerahTerimaVeneerBasah::with(['detail.ukuran', 'detail.jenisKayu'])->find($state);
                    if (! $row || ! $row->detail) {
                        return;
                    }

                    $dipakai = (int) DB::table('detail_masuks')
                        ->where('id_serah_terima_veneer_basah', $state)
                        ->sum('isi');
                    $sisa = (int) $row->detail->qty_lembar - $dipakai;

                    $set('id_jenis_kayu', $row->detail->id_jenis_kayu);
                    $set('id_ukuran', $row->detail->id_ukuran);
                    $set('kw', $row->detail->kw);
                    $set('isi', $sisa);
                    $set('sisa_tersedia', $sisa);
                })
                ->columnSpanFull(),

            Hidden::make('sisa_tersedia')->dehydrated(false),

            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id'))
                ->searchable()
                ->disabled()
                ->dehydrated(true)
                ->required(),

            Select::make('id_ukuran')
                ->label('Ukuran')
                ->options(Ukuran::all()->pluck('nama_ukuran', 'id'))
                ->searchable()
                ->disabled()
                ->dehydrated(true)
                ->required(),

            TextInput::make('kw')
                ->label('KW (Kualitas)')
                ->required()
                ->readOnly()
                ->dehydrated(true),

            TextInput::make('isi')
                ->label('Isi (Lembar)')
                ->helperText('Boleh diisi kurang dari sisa yang tersedia — sisanya tetap bisa dipakai produksi lain nanti.')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(fn (Get $get) => $get('sisa_tersedia') ?: null)
                ->dehydrated(true),

            TextInput::make('no_palet')
                ->label('Nomor Palet')
                ->numeric()
                ->default(function () use ($idProduksi, $foreignKey) {
                    if (! $idProduksi) {
                        return 1;
                    }

                    $last = (int) DB::table('detail_masuks')
                        ->where($foreignKey, $idProduksi)
                        ->max('no_palet');

                    return $last + 1;
                })
                ->required()
                ->dehydrated(true),

            Placeholder::make('info')
                ->label('')
                ->content(fn (Get $get) => filled($get('sisa_tersedia'))
                    ? "Sisa tersedia dari serah terima ini: {$get('sisa_tersedia')} lembar."
                    : '')
                ->visible(fn (Get $get) => filled($get('id_serah_terima_veneer_basah')))
                ->columnSpanFull(),
        ]);
    }

    /**
     * Alur Stik — MANUAL SEPENUHNYA.
     *
     * Sebelumnya "Nomor Palet" diambil dari palet Rotary yang sudah
     * diserahterimakan (lewat detail_hasil_palet_rotary_serah_terima_pivot).
     * Sekarang serah terima Rotary -> Stik sudah dihapus, jadi seluruh
     * field di sini diisi manual oleh operator Stik: nomor palet, jenis
     * kayu, ukuran, KW, dan isi — tidak ada lagi ketergantungan ke data
     * Rotary/pivot.
     */
    protected static function configureStikLegacy(Schema $schema, ?int $idProduksi): Schema
    {
        $foreignKey = 'id_produksi_stik';

        return $schema->schema([
            Hidden::make($foreignKey)->default($idProduksi)->required()->dehydrated(true),

            TextInput::make('no_palet')
                ->label('Nomor Palet')
                ->numeric()
                ->default(function () use ($idProduksi, $foreignKey) {
                    if (! $idProduksi) {
                        return 1;
                    }

                    $last = (int) DB::table('detail_masuk_stik')
                        ->where($foreignKey, $idProduksi)
                        ->max('no_palet');

                    return $last + 1;
                })
                ->required()
                ->dehydrated(true),

            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id'))
                ->searchable()
                ->afterStateUpdated(fn ($state) => session(['last_jenis_kayu_stik' => $state]))
                ->default(fn () => session('last_jenis_kayu_stik'))
                ->required(),

            Select::make('id_ukuran')
                ->label('Ukuran')
                ->options(Ukuran::all()->pluck('nama_ukuran', 'id'))
                ->searchable()
                ->afterStateUpdated(fn ($state) => session(['last_ukuran_stik' => $state]))
                ->default(fn () => session('last_ukuran_stik'))
                ->required(),

            TextInput::make('kw')
                ->label('KW (Kualitas)')
                ->required()
                ->placeholder('Cth: 1, 2, 3, dll.'),

            TextInput::make('isi')
                ->label('Isi (Lembar)')
                ->required()
                ->numeric()
                ->minValue(1),
        ]);
    }
}