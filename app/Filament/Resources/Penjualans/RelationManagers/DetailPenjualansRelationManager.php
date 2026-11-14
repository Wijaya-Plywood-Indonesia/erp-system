<?php

namespace App\Filament\Resources\Penjualans\RelationManagers;

use App\Models\BarangSetengahJadiHp;
use App\Models\BarangUmum;
use App\Models\DetailPenjualan;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\JenisKayu;
use App\Models\Penjualan;
use App\Models\StokPlywoodSiapJual;
use App\Models\StokVeneerJadi;
use App\Models\StokVeneerKering;
use App\Models\Ukuran;
use App\Services\PenjualanStockService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class DetailPenjualansRelationManager extends RelationManager
{
    protected static string $relationship = 'detail';

    protected static ?string $title = 'Daftar Barang Penjualan';

    protected static function labelUkuran($p, $l, $t): string
    {
        return ((float) $p) . ' cm x ' . ((float) $l) . ' cm x ' . ((float) $t) . ' mm';
    }

    protected static function dimKey($p, $l, $t): string
    {
        return ((float) $p) . '|' . ((float) $l) . '|' . ((float) $t);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('kategori_barang')
                    ->label('Kategori Barang')
                    ->options([
                        'plywood'      => 'Plywood / Multiplek (Baca Stok Siap Jual)',
                        'veneer'       => 'Veneer (Baca Stok Veneer)',
                        'barang_umum'  => 'Barang Umum (Baca Stok Barang Umum)',
                        'custom'       => 'Manual / Bebas (Tanpa Potong Stok)',
                    ])
                    ->default('plywood')
                    ->live()
                    ->required()
                    ->afterStateUpdated(function (Set $set) {
                        $set('ukuran_key', null);
                        $set('id_ukuran', null);
                        $set('id_jenis_kayu', null);
                        $set('kw_grade', null);
                        $set('id_barang_umum', null);
                        $set('harga', null);
                    }),

                // ==================== PLYWOOD SECTION ====================
                Select::make('ukuran_key')
                    ->label('Ukuran Plywood')
                    ->options(fn () => StokPlywoodSiapJual::orderBy('tebal')->get()
                        ->mapWithKeys(fn ($s) => [
                            static::dimKey($s->panjang, $s->lebar, $s->tebal) => static::labelUkuran($s->panjang, $s->lebar, $s->tebal),
                        ])
                        ->all()
                    )
                    ->placeholder('Pilih ukuran dari stok')
                    ->searchable()
                    ->visible(fn (Get $get) => $get('kategori_barang') === 'plywood')
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('id_jenis_kayu', null);
                        $set('kw_grade', null);
                        $set('harga', null);
                    }),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(function (Get $get) {
                        $cat = $get('kategori_barang');
                        if ($cat === 'plywood') {
                            $key = $get('ukuran_key');
                            if (!$key) return [];
                            $ids = StokPlywoodSiapJual::get()
                                ->filter(fn ($s) => static::dimKey($s->panjang, $s->lebar, $s->tebal) === $key)
                                ->pluck('id_jenis_kayu')
                                ->unique();
                            return JenisKayu::whereIn('id', $ids)->pluck('nama_kayu', 'id');
                        } elseif ($cat === 'veneer') {
                            return JenisKayu::pluck('nama_kayu', 'id');
                        }
                        return [];
                    })
                    ->searchable()
                    ->visible(fn (Get $get) => in_array($get('kategori_barang'), ['plywood', 'veneer']))
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('kw_grade', null);
                        $set('harga', null);
                    }),

                Select::make('kw_grade')
                    ->label('KW / Grade')
                    ->options(function (Get $get) {
                        $cat = $get('kategori_barang');
                        if ($cat === 'plywood') {
                            $key = $get('ukuran_key');
                            $idKayu = $get('id_jenis_kayu');
                            if (!$key || !$idKayu) return [];

                            return StokPlywoodSiapJual::get()
                                ->filter(fn ($s) => static::dimKey($s->panjang, $s->lebar, $s->tebal) === $key && $s->id_jenis_kayu == $idKayu)
                                ->mapWithKeys(fn ($s) => [$s->kw_grade => "{$s->kw_grade} (" . number_format((int) $s->stok_lembar) . " lbr)"])
                                ->all();
                        } elseif ($cat === 'veneer') {
                            return [
                                '1' => 'KW 1',
                                '2' => 'KW 2',
                                '3' => 'KW 3',
                                'A' => 'Grade A',
                                'B' => 'Grade B',
                                'C' => 'Grade C',
                            ];
                        }
                        return [];
                    })
                    ->searchable()
                    ->visible(fn (Get $get) => in_array($get('kategori_barang'), ['plywood', 'veneer']))
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                        self::autoGenerateNama($set, $get);
                    }),

                // ==================== VENEER SECTION ====================
                Select::make('tipe_veneer')
                    ->label('Tipe Veneer')
                    ->options([
                        'jadi'   => 'Veneer Jadi',
                        'kering' => 'Veneer Kering',
                        'basah'  => 'Veneer Basah',
                    ])
                    ->default('jadi')
                    ->visible(fn (Get $get) => $get('kategori_barang') === 'veneer')
                    ->live(),

                Select::make('id_ukuran')
                    ->label('Ukuran Veneer')
                    ->options(fn () => Ukuran::all()->mapWithKeys(fn ($u) => [$u->id => "{$u->panjang} x {$u->lebar} x {$u->tebal} mm"]))
                    ->searchable()
                    ->visible(fn (Get $get) => $get('kategori_barang') === 'veneer')
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get) => self::autoGenerateNama($set, $get)),

                // ==================== BARANG UMUM SECTION ====================
                Select::make('id_barang_umum')
                    ->label('Pilih Barang Umum')
                    ->options(fn () => BarangUmum::pluck('nama_barang', 'id'))
                    ->searchable()
                    ->visible(fn (Get $get) => $get('kategori_barang') === 'barang_umum')
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        if ($state) {
                            $b = BarangUmum::find($state);
                            if ($b) {
                                $set('nama_barang', 'Barang Umum - ' . $b->nama_barang);
                                $set('satuan', $b->satuan ?? 'pcs');
                            }
                        }
                    }),

                // ==================== DISPLAY STOK INFO ====================
                Placeholder::make('info_stok')
                    ->label('Informasi Stok Tersedia')
                    ->content(function (Get $get) {
                        $cat = $get('kategori_barang');
                        if ($cat === 'plywood') {
                            $key = $get('ukuran_key');
                            $idKayu = $get('id_jenis_kayu');
                            $kw = $get('kw_grade');
                            if (!$key || !$idKayu || !$kw) return new HtmlString('<span class="text-gray-400">Pilih ukuran, jenis kayu, dan grade...</span>');

                            $stok = StokPlywoodSiapJual::get()
                                ->first(fn ($s) => static::dimKey($s->panjang, $s->lebar, $s->tebal) === $key && $s->id_jenis_kayu == $idKayu && $s->kw_grade === $kw);
                            $lbr = $stok ? (int) $stok->stok_lembar : 0;
                            $m3 = $stok ? (float) $stok->stok_kubikasi : 0;
                            $badge = $lbr > 0 ? 'text-success-600' : 'text-danger-600';
                            return new HtmlString("<strong class=\"{$badge} text-lg\">" . number_format($lbr) . " Lembar (" . number_format($m3, 4, ',', '.') . " m³)</strong>");
                        } elseif ($cat === 'barang_umum') {
                            $idB = $get('id_barang_umum');
                            if (!$idB) return '-';
                            $b = BarangUmum::find($idB);
                            $stok = $b ? (float) $b->stok : 0;
                            $badge = $stok > 0 ? 'text-success-600' : 'text-danger-600';
                            return new HtmlString("<strong class=\"{$badge} text-lg\">" . number_format($stok) . " " . ($b->satuan ?? 'pcs') . "</strong>");
                        }
                        return new HtmlString('<span class="text-gray-400">Siap diinput</span>');
                    })
                    ->visible(fn (Get $get) => in_array($get('kategori_barang'), ['plywood', 'barang_umum'])),

                // ==================== DETAIL ITEM ====================
                TextInput::make('nama_barang')
                    ->label('Nama Barang (Tampil di Nota/SJ)')
                    ->required()
                    ->maxLength(255),

                TextInput::make('custom_nama')
                    ->label('Nama Khusus Nota Sales (Opsional)')
                    ->placeholder('Misal: 12 mm Merek Singa')
                    ->maxLength(255),

                TextInput::make('jumlah')
                    ->label('Jumlah / Qty Keluar')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get) {
                        self::recalculateKubikasi($set, $get);
                        self::recalculateSubtotal($set, $get);
                    }),

                TextInput::make('satuan')
                    ->label('Satuan')
                    ->default('Lembar')
                    ->required(),

                TextInput::make('custom_m3')
                    ->label('Kubikasi (m³)')
                    ->numeric()
                    ->step(0.0001)
                    ->nullable()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get) {
                        self::recalculateSubtotal($set, $get);
                    }),

                TextInput::make('harga')
                    ->label('Harga Satuan (Orang Kantor)')
                    ->numeric()
                    ->prefix('Rp')
                    ->nullable()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get) {
                        self::recalculateSubtotal($set, $get);
                    }),

                TextInput::make('potongan')
                    ->label('Potongan per Pcs/Lembar')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get) {
                        self::recalculateSubtotal($set, $get);
                    }),

                TextInput::make('subtotal')
                    ->label('Subtotal (Rp)')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated(),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(2)
                    ->nullable(),
            ]);
    }

    protected static function recalculateKubikasi(Set|callable $set, Get|callable $get): void
    {
        $qty = (float) ($get('jumlah') ?? 0);
        $tipe = $get('kategori_barang');

        if ($tipe === 'plywood') {
            $key = $get('ukuran_key');
            if ($key && $qty > 0) {
                [$a, $b, $tebal] = array_map('floatval', explode('|', $key));
                $m3 = ($a * $b * $tebal * $qty) / 10000000;
                $set('custom_m3', round($m3, 4));
            }
        } elseif ($tipe === 'veneer') {
            $uId = $get('id_ukuran');
            if ($uId && $qty > 0) {
                $u = Ukuran::find($uId);
                if ($u) {
                    $m3 = ((float) $u->panjang * (float) $u->lebar * (float) $u->tebal * $qty) / 10000000;
                    $set('custom_m3', round($m3, 4));
                }
            }
        }
    }

    protected static function recalculateSubtotal(Set|callable $set, Get|callable $get): void
    {
        $qty = (float) ($get('jumlah') ?? 0);
        $harga = (float) ($get('harga') ?? 0);
        $potongan = (float) ($get('potongan') ?? 0);
        $m3 = $get('custom_m3') !== null && $get('custom_m3') !== '' ? (float) $get('custom_m3') : null;

        $base = $m3 !== null ? ($m3 * $harga) : ($qty * $harga);
        $set('subtotal', max(0, $base - ($qty * $potongan)));
    }

    public static function autoGenerateNama(Set|callable $set, Get|callable $get): void
    {
        $tipe = $get('kategori_barang');
        if ($tipe === 'plywood') {
            $key = $get('ukuran_key');
            $kayuId = $get('id_jenis_kayu');
            $grade = $get('kw_grade');

            if (!$key || !$kayuId) return;

            [$a, $b, $tebal] = explode('|', $key);
            $kayu = JenisKayu::find($kayuId);
            $namaKayu = $kayu?->nama_kayu ?? '';
            $tebalStr = ((float) $tebal == (int) $tebal ? (int) $tebal : $tebal) . ' mm';

            $nama = "Plywood - {$tebalStr} - {$namaKayu} - KW {$grade}";
            $set('nama_barang', trim(preg_replace('/\s+/', ' ', $nama)));
            $set('satuan', 'Lembar');

            // Default kubikasi
            $qty = (float) ($get('jumlah') ?? 1);
            $m3 = ((float) $a * (float) $b * (float) $tebal * $qty) / 10000000;
            $set('custom_m3', round($m3, 4));

            // Default harga dari master BarangSetengahJadiHp
            [$a, $b, $tebal] = array_map('floatval', explode('|', $key));
            $matchedUkuranIds = Ukuran::where('tebal', $tebal)
                ->where(function ($q) use ($a, $b) {
                    $q->where(fn ($s) => $s->where('panjang', $a)->where('lebar', $b))
                      ->orWhere(fn ($s) => $s->where('panjang', $b)->where('lebar', $a));
                })
                ->pluck('id');

            $jenisBarang = JenisBarang::where('nama_jenis_barang', 'like', $namaKayu)->first();
            $gradeObj = Grade::whereRaw('LOWER(TRIM(nama_grade)) = ?', [strtolower(trim($grade))])->first();

            $bshp = null;
            if ($matchedUkuranIds->isNotEmpty()) {
                $bshp = BarangSetengahJadiHp::whereIn('id_ukuran', $matchedUkuranIds)
                    ->when($jenisBarang, fn ($q) => $q->where('id_jenis_barang', $jenisBarang->id))
                    ->when($gradeObj, fn ($q) => $q->where('id_grade', $gradeObj->id))
                    ->first();

                if (!$bshp) {
                    $bshp = BarangSetengahJadiHp::whereIn('id_ukuran', $matchedUkuranIds)
                        ->when($gradeObj, fn ($q) => $q->where('id_grade', $gradeObj->id))
                        ->first();
                }
            }

            if ($bshp && filled($bshp->harga)) {
                $set('harga', (float) $bshp->harga);
            }
        } elseif ($tipe === 'veneer') {
            $uId = $get('id_ukuran');
            $kId = $get('id_jenis_kayu');
            $kw = $get('kw_grade');
            $tipeVeneer = $get('tipe_veneer') ?? 'jadi';

            if (!$uId || !$kId) return;
            $u = Ukuran::find($uId);
            $k = JenisKayu::find($kId);
            $nama = 'Veneer ' . ucfirst($tipeVeneer) . ' - ' . ($u?->nama_ukuran ?? '') . ' - ' . ($k?->nama_kayu ?? '') . ' - KW ' . $kw;
            $set('nama_barang', trim(preg_replace('/\s+/', ' ', $nama)));
            $set('satuan', 'Lembar');

            $qty = (float) ($get('jumlah') ?? 1);
            if ($u) {
                $m3 = ((float) $u->panjang * (float) $u->lebar * (float) $u->tebal * $qty) / 10000000;
                $set('custom_m3', round($m3, 4));
            }
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('custom_nama')
                    ->label('Nama Sales')
                    ->placeholder('-'),
                TextColumn::make('jumlah')
                    ->label('Qty')
                    ->numeric()
                    ->alignRight(),
                TextColumn::make('satuan')
                    ->label('Satuan')
                    ->alignCenter(),
                TextColumn::make('custom_m3')
                    ->label('m³')
                    ->alignRight()
                    ->placeholder('-'),
                TextColumn::make('harga')
                    ->label('Harga (Kantor)')
                    ->money('IDR')
                    ->alignRight()
                    ->placeholder('Belum diisi'),
                TextColumn::make('potongan')
                    ->label('Pot/pcs')
                    ->money('IDR')
                    ->alignRight(),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('IDR')
                    ->alignRight(),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Barang')
                    ->mutateFormDataUsing(function (array $data): array {
                        $qty = (float) ($data['jumlah'] ?? 0);
                        $harga = (float) ($data['harga'] ?? 0);
                        $potongan = (float) ($data['potongan'] ?? 0);
                        $m3 = isset($data['custom_m3']) && $data['custom_m3'] !== null ? (float) $data['custom_m3'] : null;

                        $base = $m3 !== null ? ($m3 * $harga) : ($qty * $harga);
                        $data['subtotal'] = max(0, $base - ($qty * $potongan));
                        return $data;
                    }),

                // Tombol Validasi Penjualan & Kurangi Stok
                Action::make('validasi_dan_potong_stok')
                    ->label('Validasi & Potong Stok')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Validasi Penjualan & Potong Stok')
                    ->modalDescription('Apakah Anda yakin ingin memvalidasi penjualan ini? Stok barang akan otomatis berkurang sesuai rincian barang.')
                    ->visible(fn (RelationManager $livewire) => empty($livewire->ownerRecord->divalidasi_oleh))
                    ->action(function (RelationManager $livewire) {
                        $penjualan = $livewire->ownerRecord;

                        try {
                            app(PenjualanStockService::class)->potongStok($penjualan);

                            $penjualan->update([
                                'divalidasi_oleh' => auth()->id(),
                            ]);

                            // Otomatis kirim ke Akuntansi setelah tervalidasi
                            $msgSync = '';
                            try {
                                $cetakCtrl = app(\App\Http\Controllers\PenjualanCetakController::class);
                                [$items, $grandTotal] = $cetakCtrl->resolvePenjualanItems($penjualan, 'kantor');
                                $syncService = app(\App\Services\Akuntansi\PenjualanSyncService::class);
                                $syncRes = $syncService->kirimPenjualan($penjualan, $items, $grandTotal);

                                if ($syncRes['success']) {
                                    $penjualan->update([
                                        'status_sync_akuntansi' => 'synced',
                                        'sync_response'         => json_encode($syncRes['data'] ?? []),
                                    ]);
                                    $msgSync = ' dan transaksi berhasil dibuat di Web Akuntansi.';
                                } else {
                                    $penjualan->update([
                                        'status_sync_akuntansi' => 'failed',
                                        'sync_response'         => $syncRes['message'] ?? 'Error',
                                    ]);
                                    $msgSync = ' (Peringatan: Gagal kirim ke Akuntansi: ' . ($syncRes['message'] ?? '') . ')';
                                }
                            } catch (\Throwable $errSync) {
                                $penjualan->update([
                                    'status_sync_akuntansi' => 'failed',
                                    'sync_response'         => $errSync->getMessage(),
                                ]);
                                $msgSync = ' (Peringatan: Gagal kirim ke Akuntansi: ' . $errSync->getMessage() . ')';
                            }

                            Notification::make()
                                ->title('Penjualan Berhasil Divalidasi!')
                                ->body('Stok barang telah berhasil dipotong' . $msgSync)
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal Validasi Stok')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $qty = (float) ($data['jumlah'] ?? 0);
                        $harga = (float) ($data['harga'] ?? 0);
                        $potongan = (float) ($data['potongan'] ?? 0);
                        $m3 = isset($data['custom_m3']) && $data['custom_m3'] !== null ? (float) $data['custom_m3'] : null;

                        $base = $m3 !== null ? ($m3 * $harga) : ($qty * $harga);
                        $data['subtotal'] = max(0, $base - ($qty * $potongan));
                        return $data;
                    }),
                DeleteAction::make(),
            ]);
    }
}
