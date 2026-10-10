<?php

namespace App\Filament\Resources\ModalPilihVeneers\Schemas;

use Filament\Schemas\Schema;
use App\Models\JenisKayu;
use App\Models\StokVeneerJadi;
use App\Models\Ukuran;
use Filament\Forms\Components\Hidden;
use App\Models\PegawaiPilihVeneer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ModalPilihVeneerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. PILIH JENIS VENEER
                Select::make('jenis_veneer')
                    ->label('Jenis Veneer Input')
                    ->options([
                        'jadi'   => 'Veneer Jadi',
                        'kering' => 'Veneer Kering',
                    ])
                    ->default('jadi')
                    ->live()
                    ->required(),

                // 2A. VENEER JADI
                Select::make('id_stok_veneer_jadi')
                    ->label('Pilih Stok Veneer Jadi')
                    ->visible(fn(Get $get) => $get('jenis_veneer') === 'jadi')
                    ->options(function ($record) {
                        return StokVeneerJadi::query()
                            ->with('jenisKayu')
                            ->where('stok_lembar', '>', 0)
                            ->when($record?->id_stok_veneer_jadi, fn($q) => $q->orWhere('id', $record->id_stok_veneer_jadi))
                            ->get()
                            ->mapWithKeys(function ($stok) use ($record) {
                                $dimensi = "{$stok->panjang} x {$stok->lebar} x {$stok->tebal}";
                                $kayu    = $stok->jenisKayu?->nama_kayu ?? '-';

                                $stokTersedia = (float) $stok->stok_lembar;
                                if ($record && $record->id_stok_veneer_jadi == $stok->id) {
                                    $stokTersedia += (float) $record->jumlah;
                                }

                                $label = "{$kayu} · {$dimensi} · KW: {$stok->kw_grade} [Sisa: {$stokTersedia} lbr]";

                                return [$stok->id => $label];
                            });
                    })
                    ->searchable()
                    ->live()
                    ->required(fn(Get $get) => $get('jenis_veneer') === 'jadi')
                    ->afterStateUpdated(function ($state, Set $set, $record) {
                        if (!$state) {
                            $set('kw', null);
                            $set('sisa_stok_label', null);
                            return;
                        }

                        $stok = StokVeneerJadi::find($state);
                        if ($stok) {
                            $set('kw', $stok->kw_grade);
                            $stokTersedia = (float) $stok->stok_lembar;
                            if ($record && $record->id_stok_veneer_jadi == $stok->id) {
                                $stokTersedia += (float) $record->jumlah;
                            }
                            $set('sisa_stok_label', $stokTersedia);
                        }
                    }),

                // 2B. VENEER KERING — composite key: "{id_ukuran}-{id_jenis_kayu}-{kw}"
                Select::make('kering_key')
                    ->dehydrated(false)
                    ->label('Pilih Stok Veneer Kering')
                    ->visible(fn(Get $get) => $get('jenis_veneer') === 'kering')
                    ->options(function ($record) {
                        $stok = \Illuminate\Support\Facades\DB::table('stok_veneer_kerings')
                            ->join('ukurans', 'stok_veneer_kerings.id_ukuran', '=', 'ukurans.id')
                            ->join('jenis_kayus', 'stok_veneer_kerings.id_jenis_kayu', '=', 'jenis_kayus.id')
                            ->select(
                                'stok_veneer_kerings.id_ukuran',
                                'stok_veneer_kerings.id_jenis_kayu',
                                'stok_veneer_kerings.kw',
                                'ukurans.panjang',
                                'ukurans.lebar',
                                'ukurans.tebal',
                                'jenis_kayus.nama_kayu',
                                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN jenis_transaksi='masuk' THEN qty ELSE -qty END) as saldo_lembar")
                            )
                            ->groupBy(
                                'stok_veneer_kerings.id_ukuran',
                                'stok_veneer_kerings.id_jenis_kayu',
                                'stok_veneer_kerings.kw',
                                'ukurans.panjang',
                                'ukurans.lebar',
                                'ukurans.tebal',
                                'jenis_kayus.nama_kayu'
                            )
                            ->having('saldo_lembar', '>', 0)
                            ->get();

                        return $stok->mapWithKeys(function ($item) use ($record) {
                            $key = "{$item->id_ukuran}-{$item->id_jenis_kayu}-{$item->kw}";

                            $saldo = $item->saldo_lembar;
                            if (
                                $record &&
                                $record->jenis_veneer === 'kering' &&
                                $record->id_ukuran == $item->id_ukuran &&
                                $record->id_jenis_kayu == $item->id_jenis_kayu &&
                                $record->kw === $item->kw
                            ) {
                                $saldo += $record->jumlah;
                            }

                            $dimensi = "{$item->panjang} x {$item->lebar} x {$item->tebal}";
                            $label   = "{$item->nama_kayu} - {$dimensi} - KW: {$item->kw} [Sisa: {$saldo} lbr]";

                            return [$key => $label];
                        });
                    })
                    ->searchable()
                    ->live()
                    ->required(fn(Get $get) => $get('jenis_veneer') === 'kering')
                    // Populate form state with initialised record values when editing
                    ->afterStateHydrated(function (Set $set, Get $get, $record) {
                        if ($record && $record->jenis_veneer === 'kering' && $record->id_ukuran && $record->id_jenis_kayu && $record->kw) {
                            $set('kering_key', "{$record->id_ukuran}-{$record->id_jenis_kayu}-{$record->kw}");
                        }
                    })
                    ->afterStateUpdated(function ($state, Set $set) {
                        if (!$state) {
                            $set('kw', null);
                            $set('id_ukuran', null);
                            $set('id_jenis_kayu', null);
                            $set('sisa_stok_label', null);
                            return;
                        }

                        // Split maks 3 bagian agar kw yang berisi '-' tidak terpotong
                        [$id_ukuran, $id_jenis_kayu, $kw] = explode('-', $state, 3);

                        $set('kw', $kw);
                        $set('id_ukuran', $id_ukuran);
                        $set('id_jenis_kayu', $id_jenis_kayu);

                        $stok = \Illuminate\Support\Facades\DB::table('stok_veneer_kerings')
                            ->select(\Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN jenis_transaksi='masuk' THEN qty ELSE -qty END) as saldo_lembar"))
                            ->where('id_ukuran', $id_ukuran)
                            ->where('id_jenis_kayu', $id_jenis_kayu)
                            ->where('kw', $kw)
                            ->first();

                        $set('sisa_stok_label', (float) ($stok->saldo_lembar ?? 0));
                    }),

                // 3. FIELDS HIDDEN — yang benar-benar ada di DB
                Hidden::make('id_ukuran')->dehydrated(),
                Hidden::make('id_jenis_kayu')->dehydrated(),
                Hidden::make('kw')->dehydrated(),

                // 4. INFORMASI PENDUKUNG FORM
                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),

                TextInput::make('sisa_stok_label')
                    ->label('Stok Tersedia (Lembar)')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('jumlah')
                    ->label('Jumlah Digunakan')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 100')
                    ->rules([
                        fn(Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            if ($get('jenis_veneer') === 'jadi') {
                                $idStok = $get('id_stok_veneer_jadi');
                                if (!$idStok) return;

                                $stok = StokVeneerJadi::find($idStok);
                                if ($stok && (float)$value > (float)$stok->stok_lembar) {
                                    $fail("Jumlah input melebihi stok yang tersedia ({$stok->stok_lembar} lembar).");
                                }
                            } elseif ($get('jenis_veneer') === 'kering') {
                                $idUkuran   = $get('id_ukuran');
                                $idJenisKayu = $get('id_jenis_kayu');
                                $kw         = $get('kw');
                                if (!$idUkuran || !$idJenisKayu || !$kw) return;

                                $stok = \Illuminate\Support\Facades\DB::table('stok_veneer_kerings')
                                    ->select(\Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN jenis_transaksi='masuk' THEN qty ELSE -qty END) as saldo_lembar"))
                                    ->where('id_ukuran', $idUkuran)
                                    ->where('id_jenis_kayu', $idJenisKayu)
                                    ->where('kw', $kw)
                                    ->first();

                                $saldo = (float) ($stok->saldo_lembar ?? 0);
                                if ((float)$value > $saldo) {
                                    $fail("Jumlah input melebihi stok yang tersedia ({$saldo} lembar).");
                                }
                            }
                        },
                    ]),

                Select::make('pegawaiPilihVeneers')
                    ->label('Pegawai (Maks 2)')
                    ->relationship('pegawaiPilihVeneers', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->pegawai->nama_pegawai)
                    ->multiple()
                    ->maxItems(2)
                    ->required()
                    ->searchable()
                    ->options(function ($livewire) {
                        $produksi = $livewire->getOwnerRecord();
                        if (! $produksi) return [];
                        return \App\Models\PegawaiPilihVeneer::with('pegawai')
                            ->where('id_produksi_pilih_veneer', $produksi->id)
                            ->get()
                            ->mapWithKeys(fn ($p) => [$p->id => $p->pegawai->nama_pegawai]);
                    }),
            ]);
    }
}