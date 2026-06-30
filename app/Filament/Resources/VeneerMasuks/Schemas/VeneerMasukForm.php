<?php

namespace App\Filament\Resources\VeneerMasuks\Schemas;

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

class VeneerMasukForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->default(now())
                    ->required()
                    ->disabled(fn ($record) => $record && $record->notaBm?->divalidasi_oleh !== null),

                TextInput::make('no_nota')
                    ->label('No. Nota BM')
                    ->required()
                    ->unique('nota_barang_masuks', 'no_nota', ignorable: fn ($record) => $record?->notaBm)
                    ->disabled(fn ($record) => $record && $record->notaBm?->divalidasi_oleh !== null),

                TextInput::make('tujuan_nota')
                    ->label('Supplier / Pengirim')
                    ->required()
                    ->disabled(fn ($record) => $record && $record->notaBm?->divalidasi_oleh !== null),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->columnSpanFull()
                    ->disabled(fn ($record) => $record && $record->notaBm?->divalidasi_oleh !== null),

                Repeater::make('details')
                    ->label('Detail Barang Masuk')
                    ->relationship('details')
                    ->schema([
                        // 1. Tipe Veneer
                        Select::make('tipe_veneer')
                            ->label('Tipe Veneer')
                            ->options([
                                'basah'  => 'Veneer Basah',
                                'kering' => 'Veneer Kering',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('id_jenis_kayu', null);
                                $set('kw', null);
                                $set('id_ukuran', null);
                                $set('stok_sistem', 0);
                            })
                            ->disabled(fn ($record) => $record && $record->notaBm?->divalidasi_oleh !== null),

                        // 2. Jenis Kayu – semua jenis yang ada di DB
                        Select::make('id_jenis_kayu')
                            ->label('Jenis Kayu')
                            ->options(fn () => JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('kw', null);
                                $set('id_ukuran', null);
                                $set('stok_sistem', 0);
                            })
                            ->disabled(fn ($record) => $record && $record->notaBm?->divalidasi_oleh !== null),

                        // 3. KW / Grade – filter ke yang sudah ada di sistem untuk tipe veneer yang dipilih
                        //    (VM menerima barang, jadi tampilkan KW yang ada di DB, bukan filter stok > 0)
                        Select::make('kw')
                            ->label('KW / Grade')
                            ->options(function (Get $get) {
                                $tipeVeneer  = $get('tipe_veneer');
                                $idJenisKayu = $get('id_jenis_kayu');

                                // If no jenis kayu selected yet, show all standard grades
                                if (!$tipeVeneer || !$idJenisKayu) {
                                    return ['1' => 'KW 1', '2' => 'KW 2', '3' => 'KW 3', '4' => 'KW 4'];
                                }

                                if ($tipeVeneer === 'basah') {
                                    // Show KW that already exist in summary (any stock level)
                                    $kws = HppVeneerBasahSummary::where('id_jenis_kayu', $idJenisKayu)
                                        ->distinct()
                                        ->orderBy('kw')
                                        ->pluck('kw');
                                } else {
                                    $kws = StokVeneerKering::where('id_jenis_kayu', $idJenisKayu)
                                        ->distinct()
                                        ->orderBy('kw')
                                        ->pluck('kw');
                                }

                                // If no existing records, fallback to all grades (new jenis_kayu)
                                if ($kws->isEmpty()) {
                                    return ['1' => 'KW 1', '2' => 'KW 2', '3' => 'KW 3', '4' => 'KW 4'];
                                }

                                return $kws->mapWithKeys(fn ($kw) => [$kw => "KW {$kw}"])->toArray();
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('id_ukuran', null);
                                self::updateStokInfo($get, $set);
                            })
                            ->disabled(fn ($record) => $record && $record->notaBm?->divalidasi_oleh !== null),

                        // 4. Ukuran – filter ke yang sudah ada di DB untuk kombinasi ini
                        Select::make('id_ukuran')
                            ->label('Ukuran Barang (P x L x T)')
                            ->options(function (Get $get) {
                                $tipeVeneer  = $get('tipe_veneer');
                                $idJenisKayu = $get('id_jenis_kayu');
                                $kw          = $get('kw');

                                // If prerequisites not selected yet, show all ukuran
                                if (!$tipeVeneer || !$idJenisKayu || !$kw) {
                                    return Ukuran::orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
                                        ->get()->pluck('dimensi', 'id')->toArray();
                                }

                                if ($tipeVeneer === 'basah') {
                                    // Get dimensions from summary (any stock level)
                                    $summaries = HppVeneerBasahSummary::where('id_jenis_kayu', $idJenisKayu)
                                        ->where('kw', $kw)
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

                                    // Fallback: if no existing records, show all ukuran (new combination)
                                    if (empty($options)) {
                                        return Ukuran::orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
                                            ->get()->pluck('dimensi', 'id')->toArray();
                                    }

                                    return $options;
                                } else {
                                    $ukuranIds = StokVeneerKering::where('id_jenis_kayu', $idJenisKayu)
                                        ->where('kw', $kw)
                                        ->distinct()
                                        ->pluck('id_ukuran');

                                    if ($ukuranIds->isEmpty()) {
                                        return Ukuran::orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
                                            ->get()->pluck('dimensi', 'id')->toArray();
                                    }

                                    return Ukuran::whereIn('id', $ukuranIds)
                                        ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
                                        ->get()->pluck('dimensi', 'id')->toArray();
                                }
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateStokInfo($get, $set))
                            ->disabled(fn ($record) => $record && $record->notaBm?->divalidasi_oleh !== null),

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

                        // 6. Qty masuk
                        TextInput::make('qty')
                            ->label('Jumlah Masuk (Lembar)')
                            ->numeric()
                            ->required()
                            ->suffix('Lembar')
                            ->disabled(fn ($record) => $record && $record->notaBm?->divalidasi_oleh !== null),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->addActionLabel('Tambah Barang')
                    ->addable(fn ($record) => !$record || $record->notaBm?->divalidasi_oleh === null)
                    ->deletable(fn ($record) => !$record || $record->notaBm?->divalidasi_oleh === null)
                    ->reorderable(fn ($record) => !$record || $record->notaBm?->divalidasi_oleh === null),
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
