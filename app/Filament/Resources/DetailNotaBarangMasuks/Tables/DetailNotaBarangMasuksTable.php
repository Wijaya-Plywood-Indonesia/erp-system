<?php

namespace App\Filament\Resources\DetailNotaBarangMasuks\Tables;

use App\Models\BarangUmum;
use App\Models\DetailNotaBarangKeluar;
use App\Models\DetailNotaBarangMasuk;
use App\Models\Grade;
use App\Models\HppVeneerBasahSummary;
use App\Models\JenisKayu;
use App\Models\NotaBarangKeluar;
use App\Models\PlywoodMutasi;
use App\Models\PlywoodMutasiDetail;
use App\Models\StokLogCore;
use App\Models\StokPlywoodSiapJual;
use App\Models\StokVeneerJadi;
use App\Models\StokVeneerKering;
use App\Models\Ukuran;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use App\Services\BarangUmumInventoryService;
use App\Services\LogCoreInventoryService;
use App\Services\PlywoodMutasiService;
use App\Services\VeneerMutasiService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class DetailNotaBarangMasuksTable
{
    protected const BARANG_UMUM_PREFIX = 'Barang Umum - ';

    protected const LOG_CORE_PREFIX = 'Log Core - ';

    /**
     * Format angka qty: tanpa desimal jika bulat, tetap tampilkan desimal
     * (maks 2 digit, tanpa nol berlebih) jika pecahan.
     */
    protected static function formatQty(float $qty): string
    {
        $rounded = round($qty, 2);

        return $rounded == floor($rounded)
            ? number_format($rounded, 0)
            : rtrim(rtrim(number_format($rounded, 2), '0'), '.');
    }

    /**
     * Cari baris stok plywood tanpa peduli orientasi panjang/lebar maupun
     * beda tipe data (stok menyimpan string "122.00", ukurans cast ke float).
     */
    protected static function cariStokPlywood($ukuran, $idJenisKayu, $kw): ?StokPlywoodSiapJual
    {
        if (! $ukuran || ! $idJenisKayu || ! $kw) {
            return null;
        }

        $a = (float) $ukuran->panjang;
        $b = (float) $ukuran->lebar;

        return StokPlywoodSiapJual::where('id_jenis_kayu', $idJenisKayu)
            ->where('tebal', (float) $ukuran->tebal)
            ->where('kw_grade', $kw)
            ->where(function ($q) use ($a, $b) {
                $q->where(fn($s) => $s->where('panjang', $a)->where('lebar', $b))
                    ->orWhere(fn($s) => $s->where('panjang', $b)->where('lebar', $a));
            })
            ->first();
    }

    /**
     * Jumlah batang Log Core untuk kombinasi jenis kayu + panjang.
     */
    protected static function cariStokLogCore($idJenisKayu, $panjang): ?float
    {
        if (! $idJenisKayu || $panjang === null || $panjang === '') {
            return null;
        }

        $stok = StokLogCore::where('id_jenis_kayu', $idJenisKayu)
            ->where('panjang', (float) $panjang)
            ->first();

        return $stok ? (float) $stok->stok_qty : 0.0;
    }

    /**
     * Form fields untuk plywood — dipakai bersama oleh Tambah & Edit.
     */
    protected static function plywoodFormSchema(): array
    {
        return [
            Select::make('id_ukuran')
                ->label('Ukuran')
                ->options(Ukuran::all()->pluck('nama_ukuran', 'id'))
                ->searchable()
                ->required()
                ->live(),

            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(JenisKayu::pluck('nama_kayu', 'id'))
                ->searchable()
                ->required()
                ->live(),

            Select::make('kw_grade')
                ->label('KW / Grade')
                ->options(Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade'))
                ->searchable()
                ->required()
                ->live(),

            Placeholder::make('stok_saat_ini')
                ->label('Stok Saat Ini')
                ->content(function (callable $get) {
                    $u = $get('id_ukuran') ? Ukuran::find($get('id_ukuran')) : null;

                    if (! $u || ! $get('id_jenis_kayu') || ! $get('kw_grade')) {
                        return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Silakan lengkapi pilihan di atas...</span>');
                    }

                    $stok = static::cariStokPlywood($u, $get('id_jenis_kayu'), $get('kw_grade'));
                    $lembar = $stok ? (int) $stok->stok_lembar : 0;

                    return $lembar <= 0
                        ? new HtmlString('<strong class="text-gray-400 dark:text-gray-500 text-lg">0 Lembar (belum ada stok)</strong>')
                        : new HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">' . number_format($lembar) . ' Lembar</strong>');
                }),

            TextInput::make('jumlah')
                ->label('Jumlah (Lembar)')
                ->numeric()
                ->minValue(1)
                ->required(),

            Textarea::make('keterangan')
                ->label('Keterangan')
                ->rows(3)
                ->required(),
        ];
    }

    /**
     * Form fields untuk Barang Umum — dipakai bersama oleh Tambah & Edit.
     */
    protected static function barangUmumFormSchema(): array
    {
        return [
            Select::make('id_barang_umum')
                ->label('Barang Umum')
                ->options(BarangUmum::orderBy('nama_barang')->pluck('nama_barang', 'id'))
                ->searchable()
                ->required()
                ->live(),

            Placeholder::make('stok_saat_ini')
                ->label('Stok Saat Ini')
                ->content(function (callable $get) {
                    if (! $get('id_barang_umum')) {
                        return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Pilih barang terlebih dahulu...</span>');
                    }

                    $barang = BarangUmum::with('stok')->find($get('id_barang_umum'));

                    if (! $barang) {
                        return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Barang tidak ditemukan.</span>');
                    }

                    $qty = (float) ($barang->stok?->stok_qty ?? 0);

                    return new HtmlString(
                        '<strong class="text-success-600 dark:text-success-400 text-lg">'
                            . static::formatQty($qty) . ' ' . e($barang->satuan) . '</strong>'
                    );
                }),

            TextInput::make('jumlah')
                ->label('Jumlah')
                ->numeric()
                ->minValue(0.0001)
                ->required(),

            Textarea::make('keterangan')
                ->label('Keterangan')
                ->rows(3),
        ];
    }

    /**
     * Form Log Core untuk NOTA MASUK — jenis kayu dari master, panjang berupa input bebas.
     */
    protected static function logCoreFormSchema(): array
    {
        return [
            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(JenisKayu::pluck('nama_kayu', 'id'))
                ->searchable()
                ->required()
                ->live(),

            Select::make('panjang')
                ->label('Panjang')
                ->options(function (callable $get) {
                    $idJenisKayu = $get('id_jenis_kayu');
                    if (! $idJenisKayu) {
                        return [];
                    }

                    return StokLogCore::where('id_jenis_kayu', $idJenisKayu)
                        ->where('stok_qty', '>', 0)
                        ->get()
                        ->mapWithKeys(fn($s) => [
                            (string) $s->panjang => $s->panjang . ' cm ('
                                . static::formatQty($s->stok_qty) . ' batang)',
                        ])
                        ->all();
                })
                ->placeholder('Pilih jenis kayu dulu')
                ->searchable()
                ->required()
                ->live(),

            Placeholder::make('stok_saat_ini')
                ->label('Stok Saat Ini')
                ->content(function (callable $get) {
                    $stok = static::cariStokLogCore($get('id_jenis_kayu'), $get('panjang'));

                    if ($stok === null) {
                        return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Silakan lengkapi pilihan di atas...</span>');
                    }

                    if ($stok <= 0) {
                        return new HtmlString('<strong class="text-gray-400 dark:text-gray-500 text-lg">0 Batang (belum ada stok)</strong>');
                    }

                    return new HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">' . static::formatQty($stok) . ' Batang</strong>');
                }),

            TextInput::make('jumlah')
                ->label('Jumlah (Batang)')
                ->numeric()
                ->minValue(1)
                ->required(),

            Textarea::make('keterangan')
                ->label('Keterangan')
                ->rows(3)
                ->required(),
        ];
    }

    /**
     * Cari baris plywood_mutasi_details yang cocok dengan baris detail nota.
     */
    protected static function findPlywoodDetail($record): ?PlywoodMutasiDetail
    {
        $nota = $record->nota;

        if (! $nota || ! $nota->plywoodMutasi) {
            return null;
        }

        $details = $nota->plywoodMutasi->details()->with(['ukuran', 'jenisKayu'])->get();

        foreach ($details as $detail) {
            $ukuran = $detail->ukuran;
            $jenisKayu = $detail->jenisKayu;

            if (! $ukuran || ! $jenisKayu) {
                continue;
            }

            $expectedName = 'Plywood - ' . $ukuran->nama_ukuran
                . ' - ' . $jenisKayu->nama_kayu
                . ' - KW ' . $detail->kw_grade;

            if ($expectedName === $record->nama_barang && (int) $detail->qty === (int) $record->jumlah) {
                return $detail;
            }
        }

        return null;
    }

    /**
     * Cari baris veneer_mutasi_details yang cocok dengan baris detail nota.
     */
    protected static function findVeneerDetail($record): ?VeneerMutasiDetail
    {
        $nota = $record->nota;

        if (! $nota || ! $nota->mutasi) {
            return null;
        }

        $details = $nota->mutasi->details()->with(['ukuran', 'jenisKayu'])->get();

        foreach ($details as $detail) {
            $ukuran = $detail->ukuran;
            $jenisKayu = $detail->jenisKayu;

            if (! $ukuran || ! $jenisKayu) {
                continue;
            }

            $expectedName = 'Veneer ' . ucfirst($detail->tipe_veneer)
                . ' - ' . $ukuran->nama_ukuran
                . ' - ' . $jenisKayu->nama_kayu
                . ' - KW ' . $detail->kw;

            if ($expectedName === $record->nama_barang && (int) $detail->qty === (int) $record->jumlah) {
                return $detail;
            }
        }

        return null;
    }

    /**
     * Ambil record BarangUmum dari nama_barang detail nota (strip prefix).
     */
    protected static function findBarangUmumFromRecord($record): ?BarangUmum
    {
        if (! str_starts_with($record->nama_barang, static::BARANG_UMUM_PREFIX)) {
            return null;
        }

        $namaBarang = trim(substr($record->nama_barang, strlen(static::BARANG_UMUM_PREFIX)));

        return BarangUmum::where('nama_barang', $namaBarang)->first();
    }

    /**
     * Ambil data Log Core dari nama_barang detail nota, format:
     * "Log Core - {nama_kayu} - {panjang} cm".
     */
    public static function findLogCoreFromRecord($record): ?array
    {
        if (! str_starts_with($record->nama_barang, static::LOG_CORE_PREFIX)) {
            return null;
        }

        $sisa = substr($record->nama_barang, strlen(static::LOG_CORE_PREFIX));

        $posPanjang = strrpos($sisa, ' - ');
        if ($posPanjang === false) {
            return null;
        }

        $namaKayu = trim(substr($sisa, 0, $posPanjang));
        $panjang = (float) str_replace(' cm', '', trim(substr($sisa, $posPanjang + 3)));

        $jenisKayu = JenisKayu::where('nama_kayu', $namaKayu)->first();
        if (! $jenisKayu) {
            return null;
        }

        return [
            'id_jenis_kayu' => $jenisKayu->id,
            'panjang' => $panjang,
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id_nota_bm')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('nama_barang')
                    ->searchable(),
                TextColumn::make('jumlah')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('satuan')
                    ->searchable(),
                TextColumn::make('keterangan')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                /* ==============================================================
                 * AKSI TAMBAH ITEM DISATUKAN DALAM 1 DROPDOWN
                 * ============================================================== */
                ActionGroup::make([
                    // 1. Opsi Tambah Plywood
                    Action::make('tambah_plywood')
                        ->label('Plywood')
                        ->icon('heroicon-o-squares-2x2')
                        ->form(static::plywoodFormSchema())
                        ->action(function (RelationManager $livewire, array $data) {
                            $nota = $livewire->getOwnerRecord();
                            if (! $nota) {
                                return;
                            }

                            $isKeluar = $nota instanceof NotaBarangKeluar;

                            $mutasi = $nota->plywoodMutasi ?? PlywoodMutasi::create([
                                'tanggal' => $nota->tanggal,
                                'tipe_transaksi' => $isKeluar ? 'keluar' : 'masuk',
                                'no_nota' => $nota->no_nota,
                                'tujuan_nota' => $nota->tujuan_nota ?? '-',
                                'status' => 'draft',
                                'id_nota_bk' => $isKeluar ? $nota->id : null,
                                'id_nota_bm' => $isKeluar ? null : $nota->id,
                                'dibuat_oleh' => auth()->id(),
                            ]);

                            $ukuran = Ukuran::findOrFail($data['id_ukuran']);
                            $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                            $qty = (int) $data['jumlah'];

                            PlywoodMutasiDetail::create([
                                'id_plywood_mutasi' => $mutasi->id,
                                'id_ukuran' => $data['id_ukuran'],
                                'id_jenis_kayu' => $data['id_jenis_kayu'],
                                'kw_grade' => $data['kw_grade'],
                                'qty' => $qty,
                                'm3' => PlywoodMutasiDetail::hitungM3($ukuran, $qty),
                            ]);

                            $payload = [
                                'nama_barang' => 'Plywood - ' . $ukuran->nama_ukuran
                                    . ' - ' . $jenisKayu->nama_kayu
                                    . ' - KW ' . $data['kw_grade'],
                                'jumlah' => $qty,
                                'satuan' => 'Lembar',
                                'keterangan' => $data['keterangan'] ?? 'Otomatis dari Mutasi Plywood',
                            ];

                            $isKeluar
                                ? DetailNotaBarangKeluar::create($payload + ['id_nota_bk' => $nota->id])
                                : DetailNotaBarangMasuk::create($payload + ['id_nota_bm' => $nota->id]);

                            $livewire->dispatch('$refresh');
                        }),

                    // 2. Opsi Tambah Veneer
                    Action::make('tambah_veneer')
                        ->label('Veneer')
                        ->icon('heroicon-o-beaker')
                        ->form([
                            Select::make('tipe_veneer')
                                ->label('Tipe Veneer')
                                ->options([
                                    'basah' => 'Veneer Basah',
                                    'kering' => 'Veneer Kering',
                                    'jadi' => 'Veneer Jadi',
                                ])
                                ->required()
                                ->live(),

                            Select::make('id_ukuran')
                                ->label('Ukuran')
                                ->options(Ukuran::all()->pluck('nama_ukuran', 'id'))
                                ->searchable()
                                ->required()
                                ->live(),

                            Select::make('id_jenis_kayu')
                                ->label('Jenis Kayu')
                                ->options(JenisKayu::pluck('nama_kayu', 'id'))
                                ->searchable()
                                ->required()
                                ->live(),

                            Select::make('kw')
                                ->label('KW')
                                ->options(Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade'))
                                ->searchable()
                                ->required()
                                ->live(),

                            Placeholder::make('stok_saat_ini')
                                ->label('Stok Saat Ini')
                                ->content(function (callable $get) {
                                    $tipe = $get('tipe_veneer');
                                    $idUkuran = $get('id_ukuran');
                                    $idJenisKayu = $get('id_jenis_kayu');
                                    $kw = $get('kw');
                                    $ukuran = $idUkuran ? Ukuran::find($idUkuran) : null;

                                    if (! $tipe || ! $idUkuran || ! $idJenisKayu || ! $kw) {
                                        return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Silakan lengkapi pilihan di atas...</span>');
                                    }

                                    if ($tipe === 'basah') {
                                        if (! $ukuran) {
                                            return new HtmlString('<strong class="text-danger-600 dark:text-danger-400">0 Lembar</strong>');
                                        }

                                        $summary = HppVeneerBasahSummary::where([
                                            'id_jenis_kayu' => $idJenisKayu,
                                            'panjang' => $ukuran->panjang,
                                            'lebar' => $ukuran->lebar,
                                            'tebal' => $ukuran->tebal,
                                            'kw' => $kw,
                                        ])->first();

                                        $stok = $summary ? (int) $summary->stok_lembar : 0;
                                    } elseif ($tipe === 'jadi') {
                                        $summaryJadi = StokVeneerJadi::where([
                                            'id_jenis_kayu' => $idJenisKayu,
                                            'panjang' => $ukuran->panjang,
                                            'lebar' => $ukuran->lebar,
                                            'tebal' => $ukuran->tebal,
                                            'kw_grade' => $kw,
                                        ])->first();

                                        $stok = $summaryJadi ? (int) $summaryJadi->stok_lembar : 0;
                                    } else {
                                        $latest = StokVeneerKering::where([
                                            'id_ukuran' => $idUkuran,
                                            'id_jenis_kayu' => $idJenisKayu,
                                            'kw' => $kw,
                                        ])
                                            ->orderBy('tanggal_transaksi', 'desc')
                                            ->orderBy('id', 'desc')
                                            ->first();

                                        $stok = $latest ? (int) $latest->stok_lembar_sesudah : 0;
                                    }

                                    if ($stok <= 0) {
                                        return new HtmlString('<strong class="text-danger-600 dark:text-danger-400 text-lg">0 Lembar (Stok Habis)</strong>');
                                    }

                                    return new HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">' . number_format($stok) . ' Lembar</strong>');
                                }),

                            TextInput::make('jumlah')
                                ->label('Jumlah (Lembar)')
                                ->numeric()
                                ->required(),

                            Textarea::make('keterangan')
                                ->label('Keterangan')
                                ->rows(3)
                                ->required(),
                        ])
                        ->action(function (RelationManager $livewire, array $data) {
                            $nota = $livewire->getOwnerRecord();
                            if (! $nota) {
                                return;
                            }

                            $mutasi = $nota->mutasi;
                            $isKeluar = $nota instanceof NotaBarangKeluar;

                            if (! $mutasi) {
                                $mutasi = VeneerMutasi::create([
                                    'tanggal' => $nota->tanggal,
                                    'tipe_transaksi' => $isKeluar ? 'keluar' : 'masuk',
                                    'no_nota' => $nota->no_nota,
                                    'tujuan_nota' => $nota->tujuan_nota ?? '-',
                                    'status' => 'draft',
                                    'id_nota_bk' => $isKeluar ? $nota->id : null,
                                    'id_nota_bm' => $isKeluar ? null : $nota->id,
                                    'dibuat_oleh' => auth()->id(),
                                ]);
                            }

                            $ukuran = Ukuran::findOrFail($data['id_ukuran']);
                            $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);

                            $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * (int) $data['jumlah']) / 10000000;

                            VeneerMutasiDetail::create([
                                'id_veneer_mutasi' => $mutasi->id,
                                'tipe_veneer' => $data['tipe_veneer'],
                                'id_ukuran' => $data['id_ukuran'],
                                'id_jenis_kayu' => $data['id_jenis_kayu'],
                                'kw' => $data['kw'],
                                'qty' => (int) $data['jumlah'],
                                'm3' => $m3,
                            ]);

                            $namaBarang = 'Veneer ' . ucfirst($data['tipe_veneer'])
                                . ' - ' . $ukuran->nama_ukuran
                                . ' - ' . $jenisKayu->nama_kayu
                                . ' - KW ' . $data['kw'];

                            if ($isKeluar) {
                                DetailNotaBarangKeluar::create([
                                    'id_nota_bk' => $nota->id,
                                    'nama_barang' => $namaBarang,
                                    'jumlah' => (int) $data['jumlah'],
                                    'satuan' => 'Lembar',
                                    'keterangan' => $data['keterangan'] ?? 'Otomatis dari Mutasi Veneer Keluar',
                                ]);
                            } else {
                                DetailNotaBarangMasuk::create([
                                    'id_nota_bm' => $nota->id,
                                    'nama_barang' => $namaBarang,
                                    'jumlah' => (int) $data['jumlah'],
                                    'satuan' => 'Lembar',
                                    'keterangan' => $data['keterangan'] ?? 'Otomatis dari Mutasi Veneer Masuk',
                                ]);
                            }

                            $livewire->dispatch('$refresh');
                        }),

                    // 3. Opsi Masuk Barang Umum
                    Action::make('tambah_barang_umum')
                        ->label('Barang Umum')
                        ->icon('heroicon-o-archive-box')
                        ->form(static::barangUmumFormSchema())
                        ->action(function (RelationManager $livewire, array $data) {
                            $nota = $livewire->getOwnerRecord();
                            if (! $nota) {
                                return;
                            }

                            $barang = BarangUmum::findOrFail($data['id_barang_umum']);

                            DetailNotaBarangMasuk::create([
                                'id_nota_bm' => $nota->id,
                                'nama_barang' => static::BARANG_UMUM_PREFIX . $barang->nama_barang,
                                'jumlah' => $data['jumlah'],
                                'satuan' => $barang->satuan,
                                'keterangan' => $data['keterangan'] ?? 'Masuk dari BM Barang Umum',
                            ]);

                            $livewire->dispatch('$refresh');
                        }),

                    // 4. Opsi Masuk Log Core
                    Action::make('tambah_log_core')
                        ->label('Log Core')
                        ->icon('heroicon-o-cube')
                        ->form(static::logCoreFormSchema())
                        ->action(function (RelationManager $livewire, array $data) {
                            $nota = $livewire->getOwnerRecord();
                            if (! $nota) {
                                return;
                            }

                            $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                            $panjang = (float) $data['panjang'];
                            $qty = (int) $data['jumlah'];

                            DetailNotaBarangMasuk::create([
                                'id_nota_bm' => $nota->id,
                                'nama_barang' => static::LOG_CORE_PREFIX
                                    . $jenisKayu->nama_kayu . ' - ' . static::formatQty($panjang) . ' cm',
                                'jumlah' => $qty,
                                'satuan' => 'Batang',
                                'keterangan' => $data['keterangan'] ?? 'Otomatis dari Mutasi Log Core Masuk',
                            ]);

                            $livewire->dispatch('$refresh');
                        }),

                    // 5. Opsi Input Barang Manual
                    CreateAction::make()
                        ->label('Tambah Barang(Lainnya)')
                        ->icon('heroicon-o-plus-circle'),
                ])
                    ->label('Tambah Item Barang')
                    ->icon('heroicon-m-plus')
                    ->color('warning')
                    ->button()
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        return $nota && empty($nota->divalidasi_oleh);
                    }),

                /* ==============================================================
                 * AKSI UTAMA DOKUMEN: VALIDASI NOTA
                 * ============================================================== */
                Action::make('validasi_nota')
                    ->label('Validasi Nota')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Validasi Nota Barang Masuk')
                    ->modalDescription('Apakah Anda yakin ingin memvalidasi nota ini? Stok akan otomatis bertambah sesuai rincian barang.')
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        if (! $nota) {
                            return false;
                        }

                        if (! empty($nota->divalidasi_oleh)) {
                            return false;
                        }

                        $user = auth()->user();
                        if ($user && $user->hasAnyRole(['super_admin', 'Super Admin'])) {
                            return true;
                        }

                        return $nota->dibuat_oleh != auth()->id();
                    })
                    ->action(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        try {
                            $hasVeneer = VeneerMutasi::where('id_nota_bm', $nota->id)->exists();
                            $hasPlywood = PlywoodMutasi::where('id_nota_bm', $nota->id)->exists();
                            $hasBarangUmum = $nota->detail()
                                ->where('nama_barang', 'like', static::BARANG_UMUM_PREFIX . '%')
                                ->exists();
                            $hasLogCore = $nota->detail()
                                ->where('nama_barang', 'like', static::LOG_CORE_PREFIX . '%')
                                ->exists();

                            DB::transaction(function () use ($nota) {
                                // 1. Proses Veneer
                                app(VeneerMutasiService::class)->processStockFromNota($nota);

                                $nota->refresh();

                                // 2. Proses Plywood
                                app(PlywoodMutasiService::class)->processStockFromNota($nota);

                                // 3. Proses Barang Umum
                                app(BarangUmumInventoryService::class)->processStockFromNota($nota);

                                // 4. Proses Log Core (Penambahan stok & penulisan riwayat log)
                                app(LogCoreInventoryService::class)->processStockFromNotaMasuk($nota, auth()->id());
                            });

                            $kategoriAktif = array_filter([
                                'veneer' => $hasVeneer,
                                'plywood' => $hasPlywood,
                                'barang umum' => $hasBarangUmum,
                                'log core' => $hasLogCore,
                            ]);

                            $pesan = $kategoriAktif
                                ? 'Stok ' . implode(', ', array_keys($kategoriAktif)) . ' telah ditambahkan sesuai isi nota BM.'
                                : 'Status nota telah diperbarui.';

                            Notification::make()
                                ->title('Nota berhasil divalidasi!')
                                ->body($pesan)
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Validasi Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->after(fn($livewire) => $livewire->dispatch('$refresh')),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->form(function ($record) {
                        if (str_starts_with($record->nama_barang, DetailNotaBarangMasuksTable::BARANG_UMUM_PREFIX)) {
                            return static::barangUmumFormSchema();
                        }

                        if (str_starts_with($record->nama_barang, DetailNotaBarangMasuksTable::LOG_CORE_PREFIX)) {
                            return static::logCoreFormSchema();
                        }

                        if (str_starts_with($record->nama_barang, 'Plywood ')) {
                            return static::plywoodFormSchema();
                        }

                        if (str_starts_with($record->nama_barang, 'Veneer ')) {
                            return [
                                Select::make('tipe_veneer')
                                    ->label('Tipe Veneer')
                                    ->options([
                                        'basah' => 'Veneer Basah',
                                        'kering' => 'Veneer Kering',
                                        'jadi' => 'Veneer Jadi',
                                    ])
                                    ->required()
                                    ->live(),

                                Select::make('id_ukuran')
                                    ->label('Ukuran')
                                    ->options(Ukuran::all()->pluck('nama_ukuran', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live(),

                                Select::make('id_jenis_kayu')
                                    ->label('Jenis Kayu')
                                    ->options(JenisKayu::pluck('nama_kayu', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live(),

                                Select::make('kw')
                                    ->label('KW')
                                    ->options(Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade'))
                                    ->searchable()
                                    ->required()
                                    ->live(),

                                Placeholder::make('stok_saat_ini')
                                    ->label('Stok Saat Ini')
                                    ->content(function (callable $get) {
                                        $tipe = $get('tipe_veneer');
                                        $idUkuran = $get('id_ukuran');
                                        $idJenisKayu = $get('id_jenis_kayu');
                                        $kw = $get('kw');
                                        $ukuran = $idUkuran ? Ukuran::find($idUkuran) : null;

                                        if (! $tipe || ! $idUkuran || ! $idJenisKayu || ! $kw) {
                                            return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Silakan lengkapi pilihan di atas...</span>');
                                        }

                                        if ($tipe === 'basah') {
                                            if (! $ukuran) {
                                                return new HtmlString('<strong class="text-danger-600 dark:text-danger-400">0 Lembar</strong>');
                                            }

                                            $summary = HppVeneerBasahSummary::where([
                                                'id_jenis_kayu' => $idJenisKayu,
                                                'panjang' => $ukuran->panjang,
                                                'lebar' => $ukuran->lebar,
                                                'tebal' => $ukuran->tebal,
                                                'kw' => $kw,
                                            ])->first();

                                            $stok = $summary ? (int) $summary->stok_lembar : 0;
                                        } elseif ($tipe === 'jadi') {
                                            $summaryJadi = StokVeneerJadi::where([
                                                'id_jenis_kayu' => $idJenisKayu,
                                                'panjang' => $ukuran->panjang,
                                                'lebar' => $ukuran->lebar,
                                                'tebal' => $ukuran->tebal,
                                                'kw_grade' => $kw,
                                            ])->first();

                                            $stok = $summaryJadi ? (int) $summaryJadi->stok_lembar : 0;
                                        } else {
                                            $latest = StokVeneerKering::where([
                                                'id_ukuran' => $idUkuran,
                                                'id_jenis_kayu' => $idJenisKayu,
                                                'kw' => $kw,
                                            ])
                                                ->orderBy('tanggal_transaksi', 'desc')
                                                ->orderBy('id', 'desc')
                                                ->first();

                                            $stok = $latest ? (int) $latest->stok_lembar_sesudah : 0;
                                        }

                                        if ($stok <= 0) {
                                            return new HtmlString('<strong class="text-danger-600 dark:text-danger-400 text-lg">0 Lembar (Stok Habis)</strong>');
                                        }

                                        return new HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">' . number_format($stok) . ' Lembar</strong>');
                                    }),

                                TextInput::make('jumlah')
                                    ->label('Jumlah (Lembar)')
                                    ->numeric()
                                    ->required(),

                                Textarea::make('keterangan')
                                    ->label('Keterangan')
                                    ->rows(3)
                                    ->required(),
                            ];
                        }

                        return [
                            TextInput::make('nama_barang')
                                ->label('Nama Barang')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('jumlah')
                                ->label('Jumlah')
                                ->numeric()
                                ->required(),

                            TextInput::make('satuan')
                                ->label('Satuan')
                                ->required()
                                ->maxLength(50),

                            Textarea::make('keterangan')
                                ->label('Keterangan')
                                ->rows(3)
                                ->required(),
                        ];
                    })
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        $data['jumlah'] = (float) $record->jumlah;
                        $data['keterangan'] = $record->keterangan;

                        if (str_starts_with($record->nama_barang, DetailNotaBarangMasuksTable::BARANG_UMUM_PREFIX)) {
                            $barang = static::findBarangUmumFromRecord($record);
                            $data['id_barang_umum'] = $barang?->id;

                            return $data;
                        }

                        if (str_starts_with($record->nama_barang, DetailNotaBarangMasuksTable::LOG_CORE_PREFIX)) {
                            $logData = static::findLogCoreFromRecord($record);

                            if ($logData) {
                                $data['id_jenis_kayu'] = $logData['id_jenis_kayu'];
                                $data['panjang'] = (string) $logData['panjang'];
                            }

                            return $data;
                        }

                        if (str_starts_with($record->nama_barang, 'Plywood ')) {
                            $detail = static::findPlywoodDetail($record);

                            if ($detail) {
                                $data['id_ukuran'] = $detail->id_ukuran;
                                $data['id_jenis_kayu'] = $detail->id_jenis_kayu;
                                $data['kw_grade'] = $detail->kw_grade;
                            }

                            return $data;
                        }

                        if (str_starts_with($record->nama_barang, 'Veneer ')) {
                            $detail = static::findVeneerDetail($record);

                            if ($detail) {
                                $data['tipe_veneer'] = $detail->tipe_veneer;
                                $data['id_ukuran'] = $detail->id_ukuran;
                                $data['id_jenis_kayu'] = $detail->id_jenis_kayu;
                                $data['kw'] = $detail->kw;
                            }
                        }

                        return $data;
                    })
                    ->using(function ($record, array $data) {
                        if (str_starts_with($record->nama_barang, DetailNotaBarangMasuksTable::BARANG_UMUM_PREFIX)) {
                            $barang = BarangUmum::findOrFail($data['id_barang_umum']);

                            $record->update([
                                'nama_barang' => DetailNotaBarangMasuksTable::BARANG_UMUM_PREFIX . $barang->nama_barang,
                                'jumlah' => $data['jumlah'],
                                'satuan' => $barang->satuan,
                                'keterangan' => $data['keterangan'] ?? $record->keterangan,
                            ]);

                            return $record;
                        }

                        if (str_starts_with($record->nama_barang, DetailNotaBarangMasuksTable::LOG_CORE_PREFIX)) {
                            $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                            $panjang = (float) $data['panjang'];

                            $record->update([
                                'nama_barang' => DetailNotaBarangMasuksTable::LOG_CORE_PREFIX
                                    . $jenisKayu->nama_kayu . ' - ' . static::formatQty($panjang) . ' cm',
                                'jumlah' => (int) $data['jumlah'],
                                'keterangan' => $data['keterangan'] ?? $record->keterangan,
                            ]);

                            return $record;
                        }

                        if (str_starts_with($record->nama_barang, 'Plywood ')) {
                            $matchingDetail = static::findPlywoodDetail($record);

                            $ukuran = Ukuran::findOrFail($data['id_ukuran']);
                            $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                            $qty = (int) $data['jumlah'];

                            if ($matchingDetail) {
                                $matchingDetail->update([
                                    'id_ukuran' => $data['id_ukuran'],
                                    'id_jenis_kayu' => $data['id_jenis_kayu'],
                                    'kw_grade' => $data['kw_grade'],
                                    'qty' => $qty,
                                    'm3' => PlywoodMutasiDetail::hitungM3($ukuran, $qty),
                                ]);
                            }

                            $record->update([
                                'nama_barang' => 'Plywood - ' . $ukuran->nama_ukuran
                                    . ' - ' . $jenisKayu->nama_kayu
                                    . ' - KW ' . $data['kw_grade'],
                                'jumlah' => $qty,
                                'keterangan' => $data['keterangan'] ?? $record->keterangan,
                            ]);

                            return $record;
                        }

                        if (str_starts_with($record->nama_barang, 'Veneer ')) {
                            $matchingDetail = static::findVeneerDetail($record);

                            if ($matchingDetail) {
                                $matchingDetail->update([
                                    'tipe_veneer' => $data['tipe_veneer'],
                                    'id_ukuran' => $data['id_ukuran'],
                                    'id_jenis_kayu' => $data['id_jenis_kayu'],
                                    'kw' => $data['kw'],
                                    'qty' => (int) $data['jumlah'],
                                ]);

                                $ukuranObj = Ukuran::findOrFail($data['id_ukuran']);
                                $matchingDetail->m3 = ($ukuranObj->panjang * $ukuranObj->lebar * $ukuranObj->tebal * $matchingDetail->qty) / 10000000;
                                $matchingDetail->save();
                            }

                            $ukuran = Ukuran::findOrFail($data['id_ukuran']);
                            $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                            $newNamaBarang = 'Veneer ' . ucfirst($data['tipe_veneer'])
                                . ' - ' . $ukuran->nama_ukuran
                                . ' - ' . $jenisKayu->nama_kayu
                                . ' - KW ' . $data['kw'];

                            $record->update([
                                'nama_barang' => $newNamaBarang,
                                'jumlah' => (int) $data['jumlah'],
                                'keterangan' => $data['keterangan'] ?? $record->keterangan,
                            ]);
                        } else {
                            $record->update([
                                'nama_barang' => $data['nama_barang'],
                                'jumlah' => (int) $data['jumlah'],
                                'satuan' => $data['satuan'],
                                'keterangan' => $data['keterangan'] ?? null,
                            ]);
                        }

                        return $record;
                    })
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        return $nota && empty($nota->divalidasi_oleh);
                    }),

                DeleteAction::make()
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        return $nota && empty($nota->divalidasi_oleh);
                    })
                    ->before(function ($record) {
                        if (str_starts_with($record->nama_barang, 'Plywood ')) {
                            static::findPlywoodDetail($record)?->delete();

                            return;
                        }

                        if (str_starts_with($record->nama_barang, 'Veneer ')) {
                            static::findVeneerDetail($record)?->delete();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
