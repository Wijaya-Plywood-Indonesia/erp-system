<?php

namespace App\Filament\Resources\Penjualans\Schemas;

use App\Models\Customer;
use App\Models\Penjualan;
use App\Services\Akuntansi\CustomerSyncService;
use App\Services\NomorNotaService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class PenjualanForm
{
    protected static function isKantor(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['edmeros', 'super_admin', 'Super Admin']);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->default(today())
                    ->required()
                    ->live(),

                TextInput::make('no_nota')
                    ->label('No. Nota')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->unique(
                        table: Penjualan::class,
                        column: 'no_nota',
                        ignoreRecord: true
                    )
                    ->validationMessages([
                        'unique' => 'Nomor nota ini sudah terdaftar di sistem. Silakan gunakan nomor lain.',
                    ]),

                TextInput::make('tujuan_nota')
                    ->label('Kepada / Customer')
                    ->required()
                    ->datalist(fn () => CustomerSyncService::getCustomerOptions())
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        if ($state) {
                            $customer = Customer::where('nama', $state)->first();
                            if ($customer) {
                                if ($customer->alamat) {
                                    $set('alamat', $customer->alamat);
                                }
                                if ($customer->npwp) {
                                    $set('pembeli_bkp_npwp', $customer->npwp);
                                }
                            } else {
                                // Cari dari akuntansi
                                $syncService = app(CustomerSyncService::class);
                                $akuntansiCust = $syncService->getCustomerFromAkuntansi($state);
                                if ($akuntansiCust) {
                                    if (!empty($akuntansiCust['alamat'])) {
                                        $set('alamat', $akuntansiCust['alamat']);
                                    }
                                    if (!empty($akuntansiCust['npwp'])) {
                                        $set('pembeli_bkp_npwp', $akuntansiCust['npwp']);
                                    }
                                }
                            }

                            // Trigger simpan langsung ke Akuntansi saat blur/input
                            $alamat = $get('alamat');
                            $npwp = $get('pembeli_bkp_npwp');
                            app(CustomerSyncService::class)->syncCustomer($state, $alamat, $npwp);
                        }
                    }),

                TextInput::make('alamat')
                    ->label('Alamat')
                    ->maxLength(255)
                    ->nullable()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Get $get) {
                        $nama = $get('tujuan_nota');
                        if ($nama) {
                            $npwp = $get('pembeli_bkp_npwp');
                            app(CustomerSyncService::class)->syncCustomer($nama, $state, $npwp);
                        }
                    }),

                // ===== Field berikut hanya untuk orang kantor =====

                Toggle::make('pakai_ppn')
                    ->label('Pakai PPN (11%)')
                    ->default(false)
                    ->live()
                    ->visible(fn () => self::isKantor())
                    ->afterStateUpdated(function ($state, Set $set) {
                        $set('ppn_persen', $state ? 11 : 0);
                    }),

                Hidden::make('ppn_persen')
                    ->default(0),

                TextInput::make('pembeli_bkp_npwp')
                    ->label('NPWP Pembeli')
                    ->placeholder('Contoh: 00.000.000.0-000.000')
                    ->maxLength(100)
                    ->visible(fn ($get) => self::isKantor() && (bool) $get('pakai_ppn'))
                    ->nullable()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Get $get) {
                        $nama = $get('tujuan_nota');
                        if ($nama) {
                            $alamat = $get('alamat');
                            app(CustomerSyncService::class)->syncCustomer($nama, $alamat, $state);
                        }
                    }),

                TextInput::make('ongkir')
                    ->label('Biaya Ongkir (Rp)')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->minValue(0)
                    ->live(onBlur: true)
                    ->visible(fn () => self::isKantor()),

                Select::make('metode_pembayaran')
                    ->label('Metode Pembayaran')
                    ->options([
                        'Tunai' => 'Tunai',
                        'Transfer' => 'Transfer',
                        'Cek / Giro' => 'Cek / Giro',
                    ])
                    ->default('Tunai')
                    ->live()
                    ->visible(fn () => self::isKantor()),

                Select::make('id_rekening_perusahaan')
                    ->label('Rekening Perusahaan')
                    ->relationship('rekeningPerusahaan', 'nama_bank')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nama_bank} - {$record->no_rekening} (a/n {$record->atas_nama})")
                    ->visible(fn ($get) => self::isKantor() && $get('metode_pembayaran') === 'Transfer')
                    ->nullable(),

                TextInput::make('kendaraan')
                    ->label('Kendaraan / Ekspedisi')
                    ->placeholder('Misal: Truk Colt Diesel / Pick up')
                    ->maxLength(100)
                    ->visible(fn () => self::isKantor()),

                TextInput::make('plat_kendaraan')
                    ->label('No. Plat Kendaraan')
                    ->placeholder('Misal: AG 1234 XY')
                    ->maxLength(100)
                    ->visible(fn () => self::isKantor()),

                TextInput::make('nama_sopir')
                    ->label('Nama Sopir')
                    ->maxLength(100)
                    ->visible(fn () => self::isKantor()),

                Hidden::make('dibuat_oleh')
                    ->default(fn () => auth()->id()),

                TextInput::make('dibuat_oleh_display')
                    ->label('Dibuat Oleh')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record) {
                            $component->state($record->pembuat?->name ?? '-');
                        } else {
                            $component->state(auth()->user()?->name ?? '-');
                        }
                    }),
            ]);
    }
}
