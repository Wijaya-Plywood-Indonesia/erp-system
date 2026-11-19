<?php

namespace App\Filament\Resources\ReturPenjualans\Schemas;

use App\Models\Penjualan;
use App\Models\ReturPenjualan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ReturPenjualanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Retur & Nota Penjualan')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('tanggal')
                            ->label('Tanggal Retur')
                            ->default(today())
                            ->required(),

                        TextInput::make('no_retur')
                            ->label('No. Retur')
                            ->required()
                            ->default(fn () => 'RET-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)))
                            ->unique(ReturPenjualan::class, 'no_retur', ignoreRecord: true),

                        Select::make('penjualan_id')
                            ->label('Pilih Nota Penjualan')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->relationship('penjualan', 'no_nota')
                            ->getOptionLabelFromRecordUsing(fn (Penjualan $record) => "{$record->no_nota} - {$record->tujuan_nota} (" . ($record->tanggal ? $record->tanggal->format('d/m/Y') : '-') . ")")
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) {
                                    return;
                                }

                                $penjualan = Penjualan::with('detail')->find($state);
                                if (!$penjualan) {
                                    return;
                                }

                                $set('no_nota', $penjualan->no_nota);
                                $set('tujuan_nota', $penjualan->tujuan_nota);
                                $set('alamat', $penjualan->alamat);
                                $set('metode_pembayaran', $penjualan->metode_pembayaran ?? 'Tunai');
                                $set('kendaraan', $penjualan->kendaraan);
                                $set('plat_kendaraan', $penjualan->plat_kendaraan);
                                $set('nama_sopir', $penjualan->nama_sopir);

                                // Populate detail items from penjualan
                                $items = [];
                                foreach ($penjualan->detail as $d) {
                                    $items[] = [
                                        'detail_penjualan_id' => $d->id,
                                        'nama_barang' => $d->nama_barang,
                                        'custom_nama' => $d->custom_nama,
                                        'satuan' => $d->satuan ?? 'pcs',
                                        'qty' => (float) ($d->jumlah ?? 1),
                                        'custom_m3' => (float) ($d->custom_m3 ?? 0),
                                        'hit_kbk' => $d->hit_kbk ?? 'b',
                                        'harga' => (float) ($d->harga ?? 0),
                                        'potongan' => (float) ($d->potongan ?? 0),
                                        'subtotal' => (float) ($d->subtotal ?? 0),
                                        'keterangan' => $d->keterangan,
                                    ];
                                }
                                $set('details', $items);
                                self::recalculateTotals($set, $items);
                            }),

                        TextInput::make('no_nota')
                            ->label('Nomor Nota Asal')
                            ->readOnly()
                            ->required(),

                        TextInput::make('tujuan_nota')
                            ->label('Customer / Pembeli')
                            ->readOnly()
                            ->required(),

                        TextInput::make('alamat')
                            ->label('Alamat Customer')
                            ->nullable(),

                        Select::make('metode_pembayaran')
                            ->label('Metode Pengembalian')
                            ->options([
                                'Tunai' => 'Tunai',
                                'Transfer' => 'Transfer Bank',
                                'Potong Nota' => 'Potong Piutang / Nota',
                            ])
                            ->default('Tunai'),

                        TextInput::make('kendaraan')
                            ->label('Kendaraan')
                            ->nullable(),

                        TextInput::make('plat_kendaraan')
                            ->label('No. Polisi / Plat')
                            ->nullable(),

                        TextInput::make('nama_sopir')
                            ->label('Sopir / Kurir')
                            ->nullable(),

                        Textarea::make('keterangan')
                            ->label('Keterangan / Alasan Retur')
                            ->columnSpanFull()
                            ->nullable(),
                    ]),

                Section::make('Daftar Barang yang Diretur')
                    ->schema([
                        Repeater::make('details')
                            ->relationship('details')
                            ->schema([
                                TextInput::make('nama_barang')
                                    ->label('Nama Barang')
                                    ->required()
                                    ->columnSpan(3),

                                TextInput::make('satuan')
                                    ->label('Satuan')
                                    ->default('pcs')
                                    ->columnSpan(1),

                                TextInput::make('qty')
                                    ->label('Qty Retur')
                                    ->numeric()
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        self::updateItemSubtotal($get, $set);
                                    })
                                    ->columnSpan(1),

                                TextInput::make('custom_m3')
                                    ->label('M3 (Kubikasi)')
                                    ->numeric()
                                    ->nullable()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        self::updateItemSubtotal($get, $set);
                                    })
                                    ->columnSpan(1),

                                Select::make('hit_kbk')
                                    ->label('Hitung Dari')
                                    ->options([
                                        'b' => 'Banyak (Qty)',
                                        'm' => 'Kubikasi (M3)',
                                    ])
                                    ->default('b')
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        self::updateItemSubtotal($get, $set);
                                    })
                                    ->columnSpan(2),

                                TextInput::make('harga')
                                    ->label('Harga Satuan')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        self::updateItemSubtotal($get, $set);
                                    })
                                    ->columnSpan(2),

                                TextInput::make('potongan')
                                    ->label('Potongan')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        self::updateItemSubtotal($get, $set);
                                    })
                                    ->columnSpan(2),

                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->columnSpan(2),

                                TextInput::make('keterangan')
                                    ->label('Keterangan Kerusakan / Kondisi')
                                    ->nullable()
                                    ->columnSpan(10),
                            ])
                            ->columns(12)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                self::recalculateTotals($set, (array) $state);
                            }),
                    ]),

                Section::make('Total Pengembalian')
                    ->columns(3)
                    ->schema([
                        TextInput::make('sub_total')
                            ->label('Sub Total')
                            ->numeric()
                            ->prefix('Rp')
                            ->readOnly()
                            ->default(0),

                        TextInput::make('ppn_nominal')
                            ->label('PPN Retur')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $sub = (float) $get('sub_total');
                                $ppn = (float) $get('ppn_nominal');
                                $set('total', $sub + $ppn);
                            }),

                        TextInput::make('total')
                            ->label('Total Pengembalian')
                            ->numeric()
                            ->prefix('Rp')
                            ->readOnly()
                            ->default(0),
                    ]),
            ]);
    }

    public static function updateItemSubtotal(Get $get, Set $set): void
    {
        $qty = (float) ($get('qty') ?? 0);
        $m3 = (float) ($get('custom_m3') ?? 0);
        $hitKbk = $get('hit_kbk') ?? 'b';
        $harga = (float) ($get('harga') ?? 0);
        $potongan = (float) ($get('potongan') ?? 0);

        $multiplier = ($hitKbk === 'm' && $m3 > 0) ? $m3 : $qty;
        $subtotal = max(0, ($multiplier * $harga) - $potongan);

        $set('subtotal', $subtotal);
    }

    public static function recalculateTotals(Set $set, array $items): void
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            $m3 = (float) ($item['custom_m3'] ?? 0);
            $hitKbk = $item['hit_kbk'] ?? 'b';
            $harga = (float) ($item['harga'] ?? 0);
            $potongan = (float) ($item['potongan'] ?? 0);

            $multiplier = ($hitKbk === 'm' && $m3 > 0) ? $m3 : $qty;
            $subtotal += max(0, ($multiplier * $harga) - $potongan);
        }

        $set('sub_total', $subtotal);
        $set('total', $subtotal);
    }
}
