<?php

namespace App\Filament\Resources\DetailNotaBarangKeluars\Tables;

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
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
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

class DetailNotaBarangKeluarsTable
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
     * Key dimensi yang konsisten: tahan beda tipe data (string "122.00" vs
     * float 122) dan tahan beda urutan panjang/lebar antar tabel.
     */
    protected static function dimKey($panjang, $lebar, $tebal): string
    {
        $sisi = [(float) $panjang, (float) $lebar];
        sort($sisi);

        return implode('|', [...$sisi, (float) $tebal]);
    }

    /**
     * Label ukuran langsung dari dimensi stok, tanpa butuh master ukurans.
     */
    protected static function labelUkuran($panjang, $lebar, $tebal): string
    {
        return ((float) $panjang) . ' cm x ' . ((float) $lebar) . ' cm x ' . ((float) $tebal) . ' mm';
    }

    /**
     * Semua baris stok plywood yang masih tersedia. Sumber tunggal form KELUAR.
     */
    protected static function stokTersedia()
    {
        return StokPlywoodSiapJual::where('stok_lembar', '>', 0)
            ->orderBy('tebal')
            ->get();
    }

    /**
     * Jumlah lembar untuk kombinasi terpilih. null = pilihan belum lengkap.
     */
    protected static function cariStok($ukuranKey, $idJenisKayu, $kw): ?int
    {
        if (! $ukuranKey || ! $idJenisKayu || ! $kw) {
            return null;
        }

        $stok = static::stokTersedia()->first(
            fn($s) => static::dimKey($s->panjang, $s->lebar, $s->tebal) === $ukuranKey
                && $s->id_jenis_kayu == $idJenisKayu
                && $s->kw_grade === $kw
        );

        return $stok ? (int) $stok->stok_lembar : 0;
    }

    /**
     * Jumlah batang Log Core untuk kombinasi jenis kayu + panjang.
     * null = pilihan belum lengkap (sama seperti cariStok untuk plywood).
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
     * Terjemahkan pilihan dimensi (ukuran_key) ke baris master `ukurans`,
     * karena plywood_mutasi_details butuh id_ukuran. Dicari dua arah;
     * kalau belum ada, dibuatkan mengikuti konvensi master (sisi panjang dulu).
     */
    protected static function resolveUkuran(string $ukuranKey): Ukuran
    {
        [$a, $b, $tebal] = array_map('floatval', explode('|', $ukuranKey));

        $ukuran = Ukuran::where('tebal', $tebal)
            ->where(function ($q) use ($a, $b) {
                $q->where(fn($s) => $s->where('panjang', $a)->where('lebar', $b))
                    ->orWhere(fn($s) => $s->where('panjang', $b)->where('lebar', $a));
            })
            ->first();

        if ($ukuran) {
            return $ukuran;
        }

        $sisi = [$a, $b];
        rsort($sisi);

        return Ukuran::create([
            'panjang' => $sisi[0],
            'lebar' => $sisi[1],
            'tebal' => $tebal,
        ]);
    }

    /**
     * Form plywood untuk NOTA KELUAR — seluruh pilihan bersumber dari
     * stok_plywood_siap_jual, jadi hanya barang yang benar-benar ada
     * yang bisa dipilih.
     */
    protected static function plywoodFormSchema(): array
    {
        return [
            Select::make('ukuran_key')
                ->label('Ukuran')
                ->options(
                    fn() => static::stokTersedia()
                        ->mapWithKeys(fn($s) => [
                            static::dimKey($s->panjang, $s->lebar, $s->tebal)
                            => static::labelUkuran($s->panjang, $s->lebar, $s->tebal),
                        ])
                        ->all()
                )
                ->placeholder('Pilih ukuran yang ada stoknya')
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (callable $set) {
                    $set('id_jenis_kayu', null);
                    $set('kw_grade', null);
                }),

            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(function (callable $get) {
                    $key = $get('ukuran_key');
                    if (! $key) {
                        return [];
                    }

                    $ids = static::stokTersedia()
                        ->filter(fn($s) => static::dimKey($s->panjang, $s->lebar, $s->tebal) === $key)
                        ->pluck('id_jenis_kayu')
                        ->unique();

                    return JenisKayu::whereIn('id', $ids)->pluck('nama_kayu', 'id');
                })
                ->placeholder('Pilih ukuran dulu')
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn(callable $set) => $set('kw_grade', null)),

            Select::make('kw_grade')
                ->label('KW / Grade')
                ->options(function (callable $get) {
                    $key = $get('ukuran_key');
                    $idJenisKayu = $get('id_jenis_kayu');

                    if (! $key || ! $idJenisKayu) {
                        return [];
                    }

                    return static::stokTersedia()
                        ->filter(fn($s) => static::dimKey($s->panjang, $s->lebar, $s->tebal) === $key
                            && $s->id_jenis_kayu == $idJenisKayu)
                        ->mapWithKeys(fn($s) => [
                            $s->kw_grade => $s->kw_grade
                                . ' (' . number_format((int) $s->stok_lembar) . ' lbr)',
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
                    $lembar = static::cariStok($get('ukuran_key'), $get('id_jenis_kayu'), $get('kw_grade'));

                    if ($lembar === null) {
                        return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Silakan lengkapi pilihan di atas...</span>');
                    }

                    if ($lembar <= 0) {
                        return new HtmlString('<strong class="text-danger-600 dark:text-danger-400 text-lg">0 Lembar (Stok Habis)</strong>');
                    }

                    return new HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">' . number_format($lembar) . ' Lembar</strong>');
                }),

            TextInput::make('jumlah')
                ->label('Jumlah (Lembar)')
                ->numeric()
                ->minValue(1)
                ->maxValue(fn(callable $get) => static::cariStok(
                    $get('ukuran_key'),
                    $get('id_jenis_kayu'),
                    $get('kw_grade')
                ) ?: null)
                ->helperText('Tidak boleh melebihi stok yang tersedia.')
                ->required(),

            Textarea::make('keterangan')
                ->label('Keterangan')
                ->rows(3)
                ->required(),
        ];
    }

    /**
     * Form Barang Umum untuk NOTA KELUAR — pilihan hanya diambil dari
     * barang yang stoknya lebih dari 0, dan jumlah dibatasi maksimal
     * stok yang tersedia.
     */
    protected static function barangUmumFormSchema(): array
    {
        return [
            Select::make('id_barang_umum')
                ->label('Barang Umum')
                ->options(
                    fn() => BarangUmum::with('stok')
                        ->get()
                        ->filter(fn($b) => (float) ($b->stok?->stok_qty ?? 0) > 0)
                        ->sortBy('nama_barang')
                        ->mapWithKeys(fn($b) => [
                            $b->id => $b->nama_barang . ' (' . static::formatQty((float) $b->stok->stok_qty) . ' ' . $b->satuan . ')',
                        ])
                )
                ->placeholder('Pilih barang yang ada stoknya')
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

                    if ($qty <= 0) {
                        return new HtmlString('<strong class="text-danger-600 dark:text-danger-400 text-lg">0 ' . e($barang->satuan) . ' (Stok Habis)</strong>');
                    }

                    return new HtmlString(
                        '<strong class="text-success-600 dark:text-success-400 text-lg">'
                            . static::formatQty($qty) . ' ' . e($barang->satuan) . '</strong>'
                    );
                }),

            TextInput::make('jumlah')
                ->label('Jumlah')
                ->numeric()
                ->minValue(0.0001)
                ->maxValue(function (callable $get) {
                    if (! $get('id_barang_umum')) {
                        return null;
                    }

                    $barang = BarangUmum::with('stok')->find($get('id_barang_umum'));

                    return $barang ? ((float) ($barang->stok?->stok_qty ?? 0) ?: null) : null;
                })
                ->helperText('Tidak boleh melebihi stok yang tersedia.')
                ->required(),

            Textarea::make('keterangan')
                ->label('Keterangan')
                ->rows(3),
        ];
    }

    /**
     * Form Log Core untuk NOTA KELUAR — pilihan diambil dari stok_log_core
     * yang stok_qty > 0. Satuan selalu "Batang".
     */
    protected static function logCoreFormSchema(): array
    {
        return [
            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(
                    fn() => StokLogCore::where('stok_qty', '>', 0)
                        ->with('jenisKayu')
                        ->get()
                        ->filter(fn($s) => $s->jenisKayu !== null)
                        ->pluck('jenisKayu.nama_kayu', 'id_jenis_kayu')
                        ->unique()
                )
                ->placeholder('Pilih jenis kayu yang ada stoknya')
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn(callable $set) => $set('panjang', null)),

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
                        return new HtmlString('<strong class="text-danger-600 dark:text-danger-400 text-lg">0 Batang (Stok Habis)</strong>');
                    }

                    return new HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">' . static::formatQty($stok) . ' Batang</strong>');
                }),

            TextInput::make('jumlah')
                ->label('Jumlah (Batang)')
                ->numeric()
                ->minValue(1)
                ->maxValue(fn(callable $get) => static::cariStokLogCore(
                    $get('id_jenis_kayu'),
                    $get('panjang')
                ) ?: null)
                ->helperText('Tidak boleh melebihi stok yang tersedia.')
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
     * Ambil StokLogCore dari nama_barang detail nota, format:
     * "Log Core - {nama_kayu} - {panjang} cm". Dipakai untuk form Edit di
     * sini, dan dipakai ulang oleh LogCoreInventoryService saat validasi
     * (biar aturan parsing cuma ada di satu tempat).
     */
    public static function findLogCoreFromRecord($record): ?StokLogCore
    {
        if (! str_starts_with($record->nama_barang, static::LOG_CORE_PREFIX)) {
            return null;
        }

        $sisa = substr($record->nama_barang, strlen(static::LOG_CORE_PREFIX));

        // "{nama_kayu} - {panjang} cm" — cari ' - ' TERAKHIR, karena
        // nama_kayu sendiri berpotensi mengandung spasi/strip.
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

        return StokLogCore::where('id_jenis_kayu', $jenisKayu->id)
            ->where('panjang', $panjang)
            ->first();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nota.no_nota')
                    ->label('No Nota')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->sortable()
                    ->numeric(),

                TextColumn::make('satuan')
                    ->label('Satuan')
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->keterangan),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                /* ==============================================================
                 * REDESIGN UX: 4 AKSI TAMBAH ITEM DISATUKAN DALAM 1 DROPDOWN
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

                            $ukuran = static::resolveUkuran($data['ukuran_key']);
                            $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                            $qty = (int) $data['jumlah'];

                            PlywoodMutasiDetail::create([
                                'id_plywood_mutasi' => $mutasi->id,
                                'id_ukuran' => $ukuran->id,
                                'id_jenis_kayu' => $data['id_jenis_kayu'],
                                'kw_grade' => $data['kw_grade'],
                                'qty' => $qty,
                                'm3' => PlywoodMutasiDetail::hitungM3($ukuran, $qty),
                            ]);

                            $namaBarang = 'Plywood - ' . $ukuran->nama_ukuran
                                . ' - ' . $jenisKayu->nama_kayu
                                . ' - KW ' . $data['kw_grade'];

                            $payload = [
                                'nama_barang' => $namaBarang,
                                'jumlah' => $qty,
                                'satuan' => 'Lembar',
                                'keterangan' => $data['keterangan'] ?? 'Otomatis dari Mutasi Plywood',
                            ];

                            if ($isKeluar) {
                                DetailNotaBarangKeluar::create($payload + ['id_nota_bk' => $nota->id]);
                            } else {
                                DetailNotaBarangMasuk::create($payload + ['id_nota_bm' => $nota->id]);
                            }

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
                                ->live()
                                ->afterStateUpdated(function (callable $set) {
                                    $set('id_ukuran', null);
                                    $set('id_jenis_kayu', null);
                                    $set('kw', null);
                                }),

                            Select::make('id_ukuran')
                                ->label('Ukuran')
                                ->options(function (callable $get) {
                                    $tipe = $get('tipe_veneer');
                                    if (! $tipe) {
                                        return [];
                                    }

                                    if ($tipe === 'basah') {
                                        $availableUkuranIds = HppVeneerBasahSummary::where('stok_lembar', '>', 0)
                                            ->get()
                                            ->map(function ($summary) {
                                                return Ukuran::where([
                                                    'panjang' => $summary->panjang,
                                                    'lebar' => $summary->lebar,
                                                    'tebal' => $summary->tebal,
                                                ])->first()?->id;
                                            })
                                            ->filter()
                                            ->unique();

                                        return Ukuran::whereIn('id', $availableUkuranIds)
                                            ->get()
                                            ->pluck('nama_ukuran', 'id');
                                    } else {
                                        return Ukuran::all()->pluck('nama_ukuran', 'id');
                                    }
                                })
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (callable $set) {
                                    $set('id_jenis_kayu', null);
                                    $set('kw', null);
                                }),

                            Select::make('id_jenis_kayu')
                                ->label('Jenis Kayu')
                                ->options(function (callable $get) {
                                    $tipe = $get('tipe_veneer');
                                    $idUkuran = $get('id_ukuran');
                                    if (! $tipe || ! $idUkuran) {
                                        return [];
                                    }

                                    if ($tipe === 'basah') {
                                        $ukuran = Ukuran::find($idUkuran);
                                        if (! $ukuran) {
                                            return [];
                                        }

                                        $availableJenisKayuIds = HppVeneerBasahSummary::where([
                                            'panjang' => $ukuran->panjang,
                                            'lebar' => $ukuran->lebar,
                                            'tebal' => $ukuran->tebal,
                                        ])
                                            ->where('stok_lembar', '>', 0)
                                            ->pluck('id_jenis_kayu')
                                            ->unique();

                                        return JenisKayu::whereIn('id', $availableJenisKayuIds)
                                            ->pluck('nama_kayu', 'id');
                                    } else {
                                        return JenisKayu::pluck('nama_kayu', 'id');
                                    }
                                })
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (callable $set) {
                                    $set('kw', null);
                                }),

                            Select::make('kw')
                                ->label('KW')
                                ->options(function (callable $get) {
                                    $tipe = $get('tipe_veneer');
                                    $idUkuran = $get('id_ukuran');
                                    $idJenisKayu = $get('id_jenis_kayu');
                                    if (! $tipe || ! $idUkuran || ! $idJenisKayu) {
                                        return [];
                                    }

                                    if ($tipe === 'basah') {
                                        $ukuran = Ukuran::find($idUkuran);
                                        if (! $ukuran) {
                                            return [];
                                        }

                                        $availableKws = HppVeneerBasahSummary::where([
                                            'id_jenis_kayu' => $idJenisKayu,
                                            'panjang' => $ukuran->panjang,
                                            'lebar' => $ukuran->lebar,
                                            'tebal' => $ukuran->tebal,
                                        ])
                                            ->where('stok_lembar', '>', 0)
                                            ->pluck('kw')
                                            ->unique();
                                        $options = [];
                                        foreach ($availableKws as $kw) {
                                            $options[$kw] = 'KW ' . $kw;
                                        }

                                        return $options;
                                    } else {
                                        return Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade');
                                    }
                                })
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

                    // 3. Opsi Keluar Barang Umum
                    Action::make('keluar_barang_umum')
                        ->label('Umum')
                        ->icon('heroicon-o-archive-box')
                        ->form(static::barangUmumFormSchema())
                        ->action(function (RelationManager $livewire, array $data) {
                            $nota = $livewire->getOwnerRecord();
                            if (! $nota) {
                                return;
                            }

                            $barang = BarangUmum::with('stok')->findOrFail($data['id_barang_umum']);
                            $stokSaatIni = (float) ($barang->stok?->stok_qty ?? 0);

                            if ($stokSaatIni < (float) $data['jumlah']) {
                                Notification::make()
                                    ->danger()
                                    ->title('Stok tidak cukup')
                                    ->body("Stok {$barang->nama_barang} saat ini: {$stokSaatIni} {$barang->satuan}.")
                                    ->send();

                                return;
                            }

                            DetailNotaBarangKeluar::create([
                                'id_nota_bk' => $nota->id,
                                'nama_barang' => static::BARANG_UMUM_PREFIX . $barang->nama_barang,
                                'jumlah' => $data['jumlah'],
                                'satuan' => $barang->satuan,
                                'keterangan' => $data['keterangan'] ?? 'Keluar dari BM Barang Umum',
                            ]);

                            $livewire->dispatch('$refresh');
                        }),

                    // 4. Opsi Keluar Log Core
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

                            // Catatan: di sini SENGAJA belum menyentuh StokLogCore /
                            // LogLogCore. Sama seperti Plywood & Veneer, pengurangan
                            // stok baru terjadi saat tombol "Validasi Nota" ditekan
                            // (lihat LogCoreInventoryService), supaya baris ini masih
                            // bebas diedit/dihapus selama nota belum divalidasi.
                            DetailNotaBarangKeluar::create([
                                'id_nota_bk' => $nota->id,
                                'nama_barang' => static::LOG_CORE_PREFIX
                                    . $jenisKayu->nama_kayu . ' - ' . static::formatQty($panjang) . ' cm',
                                'jumlah' => $qty,
                                'satuan' => 'Batang',
                                'keterangan' => $data['keterangan'] ?? 'Otomatis dari Mutasi Log Core',
                            ]);

                            $livewire->dispatch('$refresh');
                        }),

                    // 5. Opsi Input Barang Manual
                    CreateAction::make()
                        ->label('Lainnya')
                        ->icon('heroicon-o-plus-circle'),
                ])
                    ->label('Tambah Item Barang')
                    ->icon('heroicon-m-plus')
                    ->color('warning') // Warna Amber
                    ->button()
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        // Hanya muncul jika nota belum divalidasi
                        return $nota && empty($nota->divalidasi_oleh);
                    }),

                /* ==============================================================
                 * AKSI UTAMA DOKUMEN: VALIDASI NOTA (STANDALONE PROMINENT BUTTON)
                 * ============================================================== */
                Action::make('validasi_nota')
                    ->label('Validasi Nota')
                    ->icon('heroicon-o-check-badge')
                    ->color('success') // Warna Hijau Prominent
                    ->requiresConfirmation()
                    ->modalHeading('Validasi Nota Barang Keluar')
                    ->modalDescription('Apakah Anda yakin ingin memvalidasi nota ini? Stok akan otomatis terpotong sesuai rincian barang.')
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->ownerRecord;
                        if (! $nota) {
                            return false;
                        }
                        // Tombol hanya muncul jika BELUM divalidasi
                        if (! empty($nota->divalidasi_oleh)) {
                            return false;
                        }
                        // Jika Super Admin, boleh lihat (bisa validasi)
                        $user = auth()->user();
                        if ($user && $user->hasAnyRole(['super_admin', 'Super Admin'])) {
                            return true;
                        }

                        // Pembuat TIDAK boleh validasi (hilangkan tombol)
                        return $nota->dibuat_oleh != auth()->id();
                    })
                    ->action(function (RelationManager $livewire) {
                        $nota = $livewire->ownerRecord;

                        try {
                            $hasVeneer = VeneerMutasi::where('id_nota_bk', $nota->id)->exists();
                            $hasPlywood = PlywoodMutasi::where('id_nota_bk', $nota->id)->exists();
                            $hasBarangUmum = $nota->detail()
                                ->where('nama_barang', 'like', static::BARANG_UMUM_PREFIX . '%')
                                ->exists();
                            $hasLogCore = $nota->detail()
                                ->where('nama_barang', 'like', static::LOG_CORE_PREFIX . '%')
                                ->exists();

                            DB::transaction(function () use ($nota) {
                                app(VeneerMutasiService::class)->processStockFromNota($nota);

                                // Pastikan divalidasi_oleh terbaca service plywood
                                $nota->refresh();

                                app(PlywoodMutasiService::class)->processStockFromNota($nota);

                                app(BarangUmumInventoryService::class)->processStockFromNotaKeluar($nota);
                                app(LogCoreInventoryService::class)->processStockFromNotaKeluar($nota, auth()->id());
                            });

                            // Dulu ini ditulis pakai match(true) dengan daftar kombinasi
                            // manual. Sekarang ada 4 kategori barang (veneer, plywood,
                            // barang umum, log core) yang berarti 16 kombinasi kalau
                            // tetap pakai pola match — jadi diganti jadi kumpulkan nama
                            // kategori yang aktif lalu digabung, lebih gampang dirawat
                            // kalau nanti nambah kategori lagi.
                            $kategoriAktif = array_filter([
                                'veneer' => $hasVeneer,
                                'plywood' => $hasPlywood,
                                'barang umum' => $hasBarangUmum,
                                'log core' => $hasLogCore,
                            ]);

                            $pesan = $kategoriAktif
                                ? 'Stok ' . implode(', ', array_keys($kategoriAktif)) . ' telah dikurangi sesuai isi nota BK.'
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
                    ->after(function ($livewire) {
                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->form(function ($record) {
                        if (str_starts_with($record->nama_barang, DetailNotaBarangKeluarsTable::BARANG_UMUM_PREFIX)) {
                            return static::barangUmumFormSchema();
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
                                    ->live()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('id_ukuran', null);
                                        $set('id_jenis_kayu', null);
                                        $set('kw', null);
                                    }),

                                Select::make('id_ukuran')
                                    ->label('Ukuran')
                                    ->options(function (callable $get) {
                                        $tipe = $get('tipe_veneer');
                                        if (! $tipe) {
                                            return [];
                                        }

                                        if ($tipe === 'basah') {
                                            $availableUkuranIds = HppVeneerBasahSummary::where('stok_lembar', '>', 0)
                                                ->get()
                                                ->map(function ($summary) {
                                                    return Ukuran::where([
                                                        'panjang' => $summary->panjang,
                                                        'lebar' => $summary->lebar,
                                                        'tebal' => $summary->tebal,
                                                    ])->first()?->id;
                                                })
                                                ->filter()
                                                ->unique();

                                            return Ukuran::whereIn('id', $availableUkuranIds)
                                                ->get()
                                                ->pluck('nama_ukuran', 'id');
                                        } else {
                                            return Ukuran::all()->pluck('nama_ukuran', 'id');
                                        }
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('id_jenis_kayu', null);
                                        $set('kw', null);
                                    }),

                                Select::make('id_jenis_kayu')
                                    ->label('Jenis Kayu')
                                    ->options(function (callable $get) {
                                        $tipe = $get('tipe_veneer');
                                        $idUkuran = $get('id_ukuran');
                                        if (! $tipe || ! $idUkuran) {
                                            return [];
                                        }

                                        if ($tipe === 'basah') {
                                            $ukuran = Ukuran::find($idUkuran);
                                            if (! $ukuran) {
                                                return [];
                                            }

                                            $availableJenisKayuIds = HppVeneerBasahSummary::where([
                                                'panjang' => $ukuran->panjang,
                                                'lebar' => $ukuran->lebar,
                                                'tebal' => $ukuran->tebal,
                                            ])
                                                ->where('stok_lembar', '>', 0)
                                                ->pluck('id_jenis_kayu')
                                                ->unique();

                                            return JenisKayu::whereIn('id', $availableJenisKayuIds)
                                                ->pluck('nama_kayu', 'id');
                                        } else {
                                            return JenisKayu::pluck('nama_kayu', 'id');
                                        }
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('kw', null);
                                    }),

                                Select::make('kw')
                                    ->label('KW')
                                    ->options(function (callable $get) {
                                        $tipe = $get('tipe_veneer');
                                        $idUkuran = $get('id_ukuran');
                                        $idJenisKayu = $get('id_jenis_kayu');
                                        if (! $tipe || ! $idUkuran || ! $idJenisKayu) {
                                            return [];
                                        }

                                        if ($tipe === 'basah') {
                                            $ukuran = Ukuran::find($idUkuran);
                                            if (! $ukuran) {
                                                return [];
                                            }

                                            $availableKws = HppVeneerBasahSummary::where([
                                                'id_jenis_kayu' => $idJenisKayu,
                                                'panjang' => $ukuran->panjang,
                                                'lebar' => $ukuran->lebar,
                                                'tebal' => $ukuran->tebal,
                                            ])
                                                ->where('stok_lembar', '>', 0)
                                                ->pluck('kw')
                                                ->unique();
                                            $options = [];
                                            foreach ($availableKws as $kw) {
                                                $options[$kw] = 'KW ' . $kw;
                                            }

                                            return $options;
                                        } else {
                                            return Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade');
                                        }
                                    })
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

                        if (str_starts_with($record->nama_barang, DetailNotaBarangKeluarsTable::LOG_CORE_PREFIX)) {
                            return static::logCoreFormSchema();
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
                        $data['jumlah'] = (int) $record->jumlah;
                        $data['keterangan'] = $record->keterangan;

                        if (str_starts_with($record->nama_barang, DetailNotaBarangKeluarsTable::BARANG_UMUM_PREFIX)) {
                            $data['jumlah'] = (float) $record->jumlah;

                            $barang = static::findBarangUmumFromRecord($record);
                            $data['id_barang_umum'] = $barang?->id;

                            return $data;
                        }

                        if (str_starts_with($record->nama_barang, DetailNotaBarangKeluarsTable::LOG_CORE_PREFIX)) {
                            $stok = static::findLogCoreFromRecord($record);

                            if ($stok) {
                                $data['id_jenis_kayu'] = $stok->id_jenis_kayu;
                                $data['panjang'] = (string) $stok->panjang;
                            }

                            return $data;
                        }

                        if (str_starts_with($record->nama_barang, 'Plywood ')) {
                            $detail = static::findPlywoodDetail($record);

                            if ($detail && $detail->ukuran) {
                                $u = $detail->ukuran;
                                $data['ukuran_key'] = static::dimKey($u->panjang, $u->lebar, $u->tebal);
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
                        if (str_starts_with($record->nama_barang, DetailNotaBarangKeluarsTable::BARANG_UMUM_PREFIX)) {
                            $barang = BarangUmum::findOrFail($data['id_barang_umum']);

                            $record->update([
                                'nama_barang' => DetailNotaBarangKeluarsTable::BARANG_UMUM_PREFIX . $barang->nama_barang,
                                'jumlah' => $data['jumlah'],
                                'satuan' => $barang->satuan,
                                'keterangan' => $data['keterangan'] ?? $record->keterangan,
                            ]);

                            return $record;
                        }

                        if (str_starts_with($record->nama_barang, DetailNotaBarangKeluarsTable::LOG_CORE_PREFIX)) {
                            $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                            $panjang = (float) $data['panjang'];

                            // Tidak perlu update LogLogCore di sini: sebelum nota
                            // divalidasi, belum ada baris log yang dibuat sama sekali
                            // (beda dengan Plywood/Veneer yang detail mutasinya sudah
                            // ada sejak "Tambah Item"). Jadi cukup update baris nota-nya.
                            $record->update([
                                'nama_barang' => DetailNotaBarangKeluarsTable::LOG_CORE_PREFIX
                                    . $jenisKayu->nama_kayu . ' - ' . static::formatQty($panjang) . ' cm',
                                'jumlah' => (int) $data['jumlah'],
                                'keterangan' => $data['keterangan'] ?? $record->keterangan,
                            ]);

                            return $record;
                        }

                        if (str_starts_with($record->nama_barang, 'Plywood ')) {
                            $matchingDetail = static::findPlywoodDetail($record);

                            $ukuran = static::resolveUkuran($data['ukuran_key']);
                            $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                            $qty = (int) $data['jumlah'];

                            if ($matchingDetail) {
                                $matchingDetail->update([
                                    'id_ukuran' => $ukuran->id,
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

                                // Recalculate m3
                                $ukuranObj = Ukuran::findOrFail($data['id_ukuran']);
                                $matchingDetail->m3 = ($ukuranObj->panjang * $ukuranObj->lebar * $ukuranObj->tebal * $matchingDetail->qty) / 10000000;
                                $matchingDetail->save();
                            }

                            // Generate new nama_barang
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
            ->toolbarActions([]);
    }
}
