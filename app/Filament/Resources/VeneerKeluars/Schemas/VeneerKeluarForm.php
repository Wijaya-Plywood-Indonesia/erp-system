<?php

namespace App\Filament\Resources\VeneerKeluars\Schemas;

use App\Models\Ukuran;
use App\Models\JenisKayu;
use App\Models\HppVeneerBasahSummary;
use App\Models\StokVeneerKering;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class VeneerKeluarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->default(now())
                    ->required()
                    ->disabled(fn($record) => $record && $record->notaBk?->divalidasi_oleh !== null),

                TextInput::make('no_nota')
                    ->label('No. Nota BK')
                    ->required()
                    ->unique('nota_barang_keluar', 'no_nota', ignorable: fn($record) => $record?->notaBk)
                    ->disabled(fn($record) => $record && $record->notaBk?->divalidasi_oleh !== null),

                TextInput::make('tujuan_nota')
                    ->label('Tujuan / Penerima')
                    ->required()
                    ->disabled(fn($record) => $record && $record->notaBk?->divalidasi_oleh !== null),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->columnSpanFull()
                    ->disabled(fn($record) => $record && $record->notaBk?->divalidasi_oleh !== null),

                Repeater::make('details')
                    ->label('Detail Barang Keluar')
                    ->relationship('details')
                    ->schema([
                        // 1. Tipe Veneer
                        Select::make('tipe_veneer')
                            ->label('Tipe Veneer')
                            ->options([
                                'basah'  => 'Veneer Basah',
                                'kering' => 'Veneer Kering',
                                'jadi' => 'Veneer Jadi',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('id_jenis_kayu', null);
                                $set('kw', null);
                                $set('id_ukuran', null);
                                $set('stok_sistem', 0);
                            })
                            ->disabled(fn($record) => $record && $record->notaBk?->divalidasi_oleh !== null),

                        // 2. Jenis Kayu – cascaded from tipe_veneer
                        Select::make('id_jenis_kayu')
                            ->label('Jenis Kayu')
                            ->options(function (Get $get) {
                                $tipeVeneer = $get('tipe_veneer');
                                if (!$tipeVeneer) return [];

                                if ($tipeVeneer === 'basah') {
                                    // Only jenis_kayu that have stock > 0
                                    $ids = HppVeneerBasahSummary::where('stok_lembar', '>', 0)
                                        ->distinct()->pluck('id_jenis_kayu');
                                    return JenisKayu::whereIn('id', $ids)
                                        ->orderBy('nama_kayu')
                                        ->pluck('nama_kayu', 'id');
                                } else {
                                    // TEMPORARY: show all jenis kayu for dry veneer
                                    return JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id');
                                }
                            })
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('kw', null);
                                $set('id_ukuran', null);
                                $set('stok_sistem', 0);
                            })
                            ->disabled(fn($record) => $record && $record->notaBk?->divalidasi_oleh !== null),

                        // 3. KW / Grade – cascaded from tipe_veneer + id_jenis_kayu
                        Select::make('kw')
                            ->label('KW / Grade')
                            ->options(function (Get $get) {
                                $tipeVeneer  = $get('tipe_veneer');
                                $idJenisKayu = $get('id_jenis_kayu');
                                if (!$tipeVeneer || !$idJenisKayu) return [];

                                if ($tipeVeneer === 'basah') {
                                    $kws = HppVeneerBasahSummary::where('id_jenis_kayu', $idJenisKayu)
                                        ->where('stok_lembar', '>', 0)
                                        ->distinct()
                                        ->orderBy('kw')
                                        ->pluck('kw');
                                    return $kws->mapWithKeys(fn($kw) => [$kw => "KW {$kw}"])->toArray();
                                } else {
                                    // TEMPORARY: show all kw for dry veneer
                                    return [
                                        '1' => 'KW 1',
                                        '2' => 'KW 2',
                                        '3' => 'KW 3',
                                        '4' => 'KW 4',
                                    ];
                                }
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('id_ukuran', null);
                                self::updateStokInfo($get, $set);
                            })
                            ->disabled(fn($record) => $record && $record->notaBk?->divalidasi_oleh !== null),

                        // 4. Ukuran – cascaded from tipe_veneer + id_jenis_kayu + kw
                        Select::make('id_ukuran')
                            ->label('Ukuran Barang (P x L x T)')
                            ->options(function (Get $get) {
                                $tipeVeneer  = $get('tipe_veneer');
                                $idJenisKayu = $get('id_jenis_kayu');
                                $kw          = $get('kw');
                                if (!$tipeVeneer || !$idJenisKayu || !$kw) return [];

                                if ($tipeVeneer === 'basah') {
                                    // Get dimensions from summary with stock > 0
                                    $summaries = HppVeneerBasahSummary::where('id_jenis_kayu', $idJenisKayu)
                                        ->where('kw', $kw)
                                        ->where('stok_lembar', '>', 0)
                                        ->get(['panjang', 'lebar', 'tebal']);

                                    $options = [];
                                    foreach ($summaries as $s) {
                                        $ukuran = Ukuran::where('panjang', $s->panjang)
                                            ->where('lebar', $s->lebar)
                                            ->where('tebal', $s->tebal)
                                            ->first();
                                        if ($ukuran) {
                                            $options[$ukuran->id] = $ukuran->dimensi;
                                        }
                                    }
                                    return $options;
                                } else {
                                    // TEMPORARY: show all sizes for dry veneer
                                    return Ukuran::orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
                                        ->get()
                                        ->pluck('dimensi', 'id')
                                        ->toArray();
                                }
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn(Get $get, Set $set) => self::updateStokInfo($get, $set))
                            ->disabled(fn($record) => $record && $record->notaBk?->divalidasi_oleh !== null),

                        // 5. Info stok (read-only)
                        TextInput::make('stok_sistem')
                            ->label('Stok Saat Ini (Sistem)')
                            ->numeric()
                            ->readOnly()
                            ->dehydrated(false)
                            ->suffix('Lembar')
                            ->afterStateHydrated(function (Set $set, Get $get) {
                                self::updateStokInfo($get, $set);
                            }),

                        // 6. Qty keluar
                        TextInput::make('qty')
                            ->label('Jumlah Keluar (Lembar)')
                            ->numeric()
                            ->required()
                            ->suffix('Lembar')
                            ->rules([
                                fn(Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $stokSistem = (int) $get('stok_sistem');
                                    if ($value > $stokSistem) {
                                        $fail("Jumlah keluar ({$value} lbr) melebihi stok ({$stokSistem} lbr).");
                                    }
                                },
                            ])
                            ->disabled(fn($record) => $record && $record->notaBk?->divalidasi_oleh !== null),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->addActionLabel('Tambah Barang')
                    ->addable(fn($record) => !$record || $record->notaBk?->divalidasi_oleh === null)
                    ->deletable(fn($record) => !$record || $record->notaBk?->divalidasi_oleh === null)
                    ->reorderable(fn($record) => !$record || $record->notaBk?->divalidasi_oleh === null),
            ]);
    }

    public static function updateStokInfo(Get $get, Set $set): void
    {
        $idUkuran    = $get('id_ukuran');
        $idJenisKayu = $get('id_jenis_kayu');
        $kw          = $get('kw');
        $tipeVeneer  = $get('tipe_veneer');

        if (!$idUkuran || !$idJenisKayu || !$kw || !$tipeVeneer) {
            $set('stok_sistem', 0);
            return;
        }

        $ukuran = Ukuran::find($idUkuran);
        if (!$ukuran) return;

        if ($tipeVeneer === 'basah') {
            $summary = HppVeneerBasahSummary::where([
                'id_jenis_kayu' => $idJenisKayu,
                'panjang'       => $ukuran->panjang,
                'lebar'         => $ukuran->lebar,
                'tebal'         => $ukuran->tebal,
                'kw'            => $kw,
            ])->first();

            $set('stok_sistem', $summary ? (int) $summary->stok_lembar : 0);
        } else {
            $stokKering = StokVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);
            $set('stok_sistem', $stokKering);
        }
    }
}
