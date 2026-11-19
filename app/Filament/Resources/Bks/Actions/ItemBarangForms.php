<?php

namespace App\Filament\Resources\Bks\Actions;

use App\Models\BarangSetengahJadiHp;
use App\Models\BarangUmum;
use App\Models\Grade;
use App\Models\HppVeneerBasahSummary;
use App\Models\JenisBarang;
use App\Models\JenisKayu;
use App\Models\StokLogCore;
use App\Models\StokPlatformJadi;
use App\Models\StokPlatformMth;
use App\Models\StokPlywoodSiapJual;
use App\Models\StokTriplekJadi;
use App\Models\StokTriplekMth;
use App\Models\StokVeneerJadi;
use App\Models\StokVeneerKering;
use App\Models\Ukuran;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Form pengisian barang untuk BK (keluar) dan BM (masuk).
 * Meniru form menu lama: BK memfilter pilihan berdasarkan stok yang ada dan menampilkan stok,
 * BM menampilkan semua pilihan master dan stok sebagai info saja.
 */
class ItemBarangForms
{
    /* ───────────── pembantu umum ───────────── */

    public static function fmt(float $qty): string
    {
        $r = round($qty, 2);
        return $r == floor($r) ? number_format($r, 0) : rtrim(rtrim(number_format($r, 2), '0'), '.');
    }

    public static function dimKey($p, $l, $t): string
    {
        $sisi = [(float) $p, (float) $l];
        sort($sisi);
        return implode('|', [...$sisi, (float) $t]);
    }

    public static function labelUkuran($p, $l, $t): string
    {
        return ((float) $p) . ' cm x ' . ((float) $l) . ' cm x ' . ((float) $t) . ' mm';
    }

    private static function belum(): HtmlString
    {
        return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Silakan lengkapi pilihan di atas...</span>');
    }

    /** Tampilan stok. $keluar: stok 0 ditandai merah; masuk: abu (belum ada stok). */
    private static function tampilStok(float|int|null $stok, string $satuan, bool $keluar): HtmlString
    {
        if ($stok === null) {
            return self::belum();
        }
        if ($stok <= 0) {
            return $keluar
                ? new HtmlString('<strong class="text-danger-600 dark:text-danger-400 text-lg">' . self::fmt((float) $stok) . " {$satuan} (Stok Habis/Minus)</strong>")
                : new HtmlString("<strong class=\"text-gray-400 dark:text-gray-500 text-lg\">0 {$satuan} (belum ada stok)</strong>");
        }
        return new HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">' . self::fmt((float) $stok) . " {$satuan}</strong>");
    }

    private static function bisaLihatHarga(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['edmeros', 'super_admin', 'Super Admin']);
    }

    private static function cariUkuran($p, $l, $t): ?Ukuran
    {
        return Ukuran::where('tebal', (float) $t)
            ->where(fn ($q) => $q->where(fn ($s) => $s->where('panjang', (float) $p)->where('lebar', (float) $l))
                ->orWhere(fn ($s) => $s->where('panjang', (float) $l)->where('lebar', (float) $p)))
            ->first();
    }

    /** Cocok persis panjang/lebar/tebal (tanpa menukar arah), seperti form veneer menu lama. */
    private static function cariUkuranPersis($p, $l, $t): ?Ukuran
    {
        return Ukuran::where(['panjang' => (float) $p, 'lebar' => (float) $l, 'tebal' => (float) $t])->first();
    }

    private static function samaPersis(array $s, Ukuran $u): bool
    {
        return (float) $s['p'] === (float) $u->panjang && (float) $s['l'] === (float) $u->lebar && (float) $s['t'] === (float) $u->tebal;
    }

    public static function resolveUkuran(string $key): Ukuran
    {
        [$a, $b, $t] = array_map('floatval', explode('|', $key));
        return self::cariUkuran($a, $b, $t)
            ?? throw new \RuntimeException('Ukuran ' . self::labelUkuran($a, $b, $t) . ' belum ada di master Ukuran.');
    }

    private static function hargaDefault(?Ukuran $ukuran, $idJenisKayu, ?string $kw): ?float
    {
        if (! $ukuran || ! $idJenisKayu || ! $kw) {
            return null;
        }
        $jk = JenisKayu::find($idJenisKayu);
        $jb = JenisBarang::where('nama_jenis_barang', 'like', $jk?->nama_kayu)->first();
        $grade = Grade::whereRaw('LOWER(TRIM(nama_grade)) = ?', [strtolower(trim($kw))])->first();

        $bshp = BarangSetengahJadiHp::where('id_ukuran', $ukuran->id)
            ->when($jb, fn ($q) => $q->where('id_jenis_barang', $jb->id))
            ->when($grade, fn ($q) => $q->where('id_grade', $grade->id))
            ->first()
            ?? BarangSetengahJadiHp::where('id_ukuran', $ukuran->id)
                ->when($grade, fn ($q) => $q->where('id_grade', $grade->id))->first();

        return $bshp && filled($bshp->harga) ? (float) $bshp->harga : null;
    }

    private static function fieldsAkhir(bool $harga = false): array
    {
        $f = [TextInput::make('jumlah')->label('Jumlah (Lembar)')->numeric()->minValue(1)->required()];
        if ($harga) {
            $f[] = TextInput::make('harga')->label('Harga')->numeric()->prefix('Rp')
                ->hidden(fn () => ! self::bisaLihatHarga());
        }
        $f[] = Textarea::make('keterangan')->label('Keterangan')->rows(3)->required();
        return $f;
    }

    /* ───────────── PLYWOOD ───────────── */

    private static function stokPlywood()
    {
        return StokPlywoodSiapJual::orderBy('tebal')->get();
    }

    private static function stokPlywoodCari($ukuranKey, $idKayu, $kw): ?int
    {
        if (! $ukuranKey || ! $idKayu || ! $kw) {
            return null;
        }
        $s = self::stokPlywood()->first(fn ($s) => self::dimKey($s->panjang, $s->lebar, $s->tebal) === $ukuranKey
            && $s->id_jenis_kayu == $idKayu && $s->kw_grade === $kw);
        return $s ? (int) $s->stok_lembar : 0;
    }

    public static function plywood(bool $keluar): array
    {
        if (! $keluar) {
            return [
                Select::make('id_ukuran')->label('Ukuran')->options(fn () => Ukuran::all()->pluck('nama_ukuran', 'id'))
                    ->searchable()->required()->live(),
                Select::make('id_jenis_kayu')->label('Jenis Kayu')->options(fn () => JenisKayu::pluck('nama_kayu', 'id'))
                    ->searchable()->required()->live(),
                Select::make('kw_grade')->label('KW / Grade')
                    ->options(fn () => Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade'))
                    ->searchable()->required()->live(),
                Placeholder::make('stok_saat_ini')->label('Stok Saat Ini')->content(function (Get $get) {
                    $u = $get('id_ukuran') ? Ukuran::find($get('id_ukuran')) : null;
                    if (! $u || ! $get('id_jenis_kayu') || ! $get('kw_grade')) {
                        return self::belum();
                    }
                    $s = StokPlywoodSiapJual::where('id_jenis_kayu', $get('id_jenis_kayu'))->where('tebal', (float) $u->tebal)
                        ->where('kw_grade', $get('kw_grade'))
                        ->where(fn ($q) => $q->where(fn ($x) => $x->where('panjang', $u->panjang)->where('lebar', $u->lebar))
                            ->orWhere(fn ($x) => $x->where('panjang', $u->lebar)->where('lebar', $u->panjang)))->first();
                    return self::tampilStok((int) ($s->stok_lembar ?? 0), 'Lembar', false);
                }),
                ...self::fieldsAkhir(),
            ];
        }

        return [
            Select::make('ukuran_key')->label('Ukuran')
                ->options(fn () => self::stokPlywood()->mapWithKeys(fn ($s) => [
                    self::dimKey($s->panjang, $s->lebar, $s->tebal) => self::labelUkuran($s->panjang, $s->lebar, $s->tebal),
                ])->all())
                ->placeholder('Pilih ukuran yang ada stoknya')->searchable()->required()->live()
                ->afterStateUpdated(function (Set $set) { $set('id_jenis_kayu', null); $set('kw_grade', null); $set('harga', null); }),

            Select::make('id_jenis_kayu')->label('Jenis Kayu')
                ->options(function (Get $get) {
                    $key = $get('ukuran_key');
                    if (! $key) {
                        return [];
                    }
                    $ids = self::stokPlywood()->filter(fn ($s) => self::dimKey($s->panjang, $s->lebar, $s->tebal) === $key)
                        ->pluck('id_jenis_kayu')->unique();
                    return JenisKayu::whereIn('id', $ids)->pluck('nama_kayu', 'id');
                })
                ->placeholder('Pilih ukuran dulu')->searchable()->required()->live()
                ->afterStateUpdated(function (Set $set) { $set('kw_grade', null); $set('harga', null); }),

            Select::make('kw_grade')->label('KW / Grade')
                ->options(function (Get $get) {
                    if (! $get('ukuran_key') || ! $get('id_jenis_kayu')) {
                        return [];
                    }
                    return self::stokPlywood()
                        ->filter(fn ($s) => self::dimKey($s->panjang, $s->lebar, $s->tebal) === $get('ukuran_key') && $s->id_jenis_kayu == $get('id_jenis_kayu'))
                        ->mapWithKeys(fn ($s) => [$s->kw_grade => $s->kw_grade . ' (' . number_format((int) $s->stok_lembar) . ' lbr)'])->all();
                })
                ->placeholder('Pilih jenis kayu dulu')->searchable()->required()->live()
                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                    $u = $get('ukuran_key') ? self::cariUkuranDariKey($get('ukuran_key')) : null;
                    $h = self::hargaDefault($u, $get('id_jenis_kayu'), $state);
                    if ($h !== null) {
                        $set('harga', $h);
                    }
                }),

            Placeholder::make('stok_saat_ini')->label('Stok Saat Ini')->content(
                fn (Get $get) => self::tampilStok(self::stokPlywoodCari($get('ukuran_key'), $get('id_jenis_kayu'), $get('kw_grade')), 'Lembar', true)
            ),
            ...self::fieldsAkhir(true),
        ];
    }

    /* ───────────── PLYWOOD: pilih tipe (Plywood Siap Jual / Triplek Jadi / Triplek Mth) ───────────── */

    public static function plywoodTipe(bool $keluar): array
    {
        $siap = fn (Get $get) => ($get('tipe_plywood') ?? 'siap_jual') === 'siap_jual';
        $triplek = fn (Get $get) => in_array($get('tipe_plywood'), ['triplek_jadi', 'triplek_mth'], true);

        return [
            Select::make('tipe_plywood')->label('Tipe Plywood')->required()->live()->default('siap_jual')
                ->options([
                    'siap_jual' => 'Plywood Siap Jual',
                    'triplek_jadi' => 'Triplek Jadi',
                    'triplek_mth' => 'Triplek Mentah (Mth / Setengah Jadi)',
                ]),
            Group::make(self::plywood($keluar))->visible($siap)->columnSpanFull(),
            Group::make([
                Select::make('tp_id_ukuran')->label('Ukuran')->options(fn () => Ukuran::all()->pluck('nama_ukuran', 'id'))
                    ->searchable()->required($triplek)->live(),
                Select::make('tp_id_jenis_kayu')->label('Jenis Kayu')->options(fn () => JenisKayu::pluck('nama_kayu', 'id'))
                    ->searchable()->required($triplek)->live(),
                Select::make('tp_kw_grade')->label('KW / Grade')
                    ->options(fn () => Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade'))
                    ->searchable()->required($triplek)->live(),
                Placeholder::make('tp_stok')->label('Stok Saat Ini')->content(function (Get $get) use ($keluar) {
                    $u = $get('tp_id_ukuran') ? Ukuran::find($get('tp_id_ukuran')) : null;
                    if (! $u || ! $get('tp_id_jenis_kayu') || ! $get('tp_kw_grade')) {
                        return self::belum();
                    }
                    $model = $get('tipe_plywood') === 'triplek_mth' ? StokTriplekMth::class : StokTriplekJadi::class;
                    $s = $model::where('id_jenis_kayu', $get('tp_id_jenis_kayu'))->where('tebal', (float) $u->tebal)
                        ->where('kw_grade', $get('tp_kw_grade'))
                        ->where(fn ($q) => $q->where(fn ($x) => $x->where('panjang', $u->panjang)->where('lebar', $u->lebar))
                            ->orWhere(fn ($x) => $x->where('panjang', $u->lebar)->where('lebar', $u->panjang)))->first();
                    return self::tampilStok((int) ($s->stok_lembar ?? 0), 'Lembar', $keluar);
                }),
                TextInput::make('tp_jumlah')->label('Jumlah (Lembar)')->numeric()->minValue(1)->required($triplek)
                    ->helperText($keluar ? 'Kecukupan stok dicek ulang saat nota divalidasi.' : null),
                Textarea::make('tp_keterangan')->label('Keterangan')->rows(3)->required($triplek),
            ])->visible($triplek)->columnSpanFull(),
        ];
    }

    private static function cariUkuranDariKey(string $key): ?Ukuran
    {
        [$a, $b, $t] = array_map('floatval', explode('|', $key));
        return self::cariUkuran($a, $b, $t);
    }

    /* ───────────── VENEER ───────────── */

    /** Baris stok veneer yang ada isinya, seragam untuk 3 jenis: [panjang, lebar, tebal, id_jenis_kayu, kw, stok]. */
    private static function stokVeneer(string $tipe): \Illuminate\Support\Collection
    {
        if ($tipe === 'basah') {
            return HppVeneerBasahSummary::where('stok_lembar', '>', 0)->get()->map(fn ($s) => [
                'p' => $s->panjang, 'l' => $s->lebar, 't' => $s->tebal, 'kayu' => $s->id_jenis_kayu, 'kw' => (string) $s->kw, 'stok' => (int) $s->stok_lembar,
            ]);
        }
        if ($tipe === 'jadi') {
            return StokVeneerJadi::where('stok_lembar', '>', 0)->get()->map(fn ($s) => [
                'p' => $s->panjang, 'l' => $s->lebar, 't' => $s->tebal, 'kayu' => $s->id_jenis_kayu, 'kw' => (string) $s->kw_grade, 'stok' => (int) $s->stok_lembar,
            ]);
        }
        // kering: buku besar (ledger), saldo = baris terakhir tiap kombinasi
        $ids = StokVeneerKering::select(DB::raw('MAX(id) as id'))->groupBy('id_ukuran', 'id_jenis_kayu', 'kw')->pluck('id');
        $ukuran = Ukuran::all()->keyBy('id');
        return StokVeneerKering::whereIn('id', $ids)->where('stok_lembar_sesudah', '>', 0)->get()
            ->filter(fn ($s) => $ukuran->has($s->id_ukuran))
            ->map(fn ($s) => [
                'p' => $ukuran[$s->id_ukuran]->panjang, 'l' => $ukuran[$s->id_ukuran]->lebar, 't' => $ukuran[$s->id_ukuran]->tebal,
                'kayu' => $s->id_jenis_kayu, 'kw' => (string) $s->kw, 'stok' => (int) $s->stok_lembar_sesudah,
            ]);
    }

    private static function stokVeneerCari(?string $tipe, $idUkuran, $idKayu, $kw): ?int
    {
        if (! $tipe || ! $idUkuran || ! $idKayu || ! $kw) {
            return null;
        }
        $u = Ukuran::find($idUkuran);
        if (! $u) {
            return 0;
        }
        if ($tipe === 'basah') {
            return (int) (HppVeneerBasahSummary::where(['id_jenis_kayu' => $idKayu, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw' => $kw])->value('stok_lembar') ?? 0);
        }
        if ($tipe === 'jadi') {
            return (int) (StokVeneerJadi::where(['id_jenis_kayu' => $idKayu, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->value('stok_lembar') ?? 0);
        }
        $last = StokVeneerKering::where(['id_ukuran' => $idUkuran, 'id_jenis_kayu' => $idKayu, 'kw' => $kw])
            ->orderBy('tanggal_transaksi', 'desc')->orderBy('id', 'desc')->first();
        return (int) ($last->stok_lembar_sesudah ?? 0);
    }

    public static function veneer(bool $keluar): array
    {
        $reset = fn (Set $set) => [$set('id_ukuran', null), $set('id_jenis_kayu', null), $set('kw', null)];

        return [
            Select::make('tipe_veneer')->label('Tipe Veneer')->required()->live()
                ->options(['basah' => 'Veneer Basah', 'kering' => 'Veneer Kering', 'jadi' => 'Veneer Jadi'])
                ->afterStateUpdated(fn (Set $set) => $reset($set)),

            Select::make('id_ukuran')->label('Ukuran')->searchable()->required()->live()
                ->options(function (Get $get) use ($keluar) {
                    $tipe = $get('tipe_veneer');
                    if (! $tipe) {
                        return [];
                    }
                    if (! $keluar) {
                        return Ukuran::all()->pluck('nama_ukuran', 'id');
                    }
                    $ids = self::stokVeneer($tipe)->map(fn ($s) => self::cariUkuranPersis($s['p'], $s['l'], $s['t'])?->id)->filter()->unique();
                    return Ukuran::whereIn('id', $ids)->get()->pluck('nama_ukuran', 'id');
                })
                ->afterStateUpdated(function (Set $set) { $set('id_jenis_kayu', null); $set('kw', null); }),

            Select::make('id_jenis_kayu')->label('Jenis Kayu')->searchable()->required()->live()
                ->options(function (Get $get) use ($keluar) {
                    $tipe = $get('tipe_veneer');
                    $u = $get('id_ukuran') ? Ukuran::find($get('id_ukuran')) : null;
                    if (! $tipe || ! $u) {
                        return [];
                    }
                    if (! $keluar) {
                        return JenisKayu::pluck('nama_kayu', 'id');
                    }
                    $ids = self::stokVeneer($tipe)
                        ->filter(fn ($s) => self::samaPersis($s, $u))
                        ->pluck('kayu')->unique();
                    return JenisKayu::whereIn('id', $ids)->pluck('nama_kayu', 'id');
                })
                ->afterStateUpdated(fn (Set $set) => $set('kw', null)),

            Select::make('kw')->label('KW')->searchable()->required()->live()
                ->options(function (Get $get) use ($keluar) {
                    $tipe = $get('tipe_veneer');
                    $u = $get('id_ukuran') ? Ukuran::find($get('id_ukuran')) : null;
                    if (! $tipe || ! $u || ! $get('id_jenis_kayu')) {
                        return [];
                    }
                    if (! $keluar) {
                        return Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade');
                    }
                    return self::stokVeneer($tipe)
                        ->filter(fn ($s) => self::samaPersis($s, $u) && $s['kayu'] == $get('id_jenis_kayu'))
                        ->mapWithKeys(fn ($s) => [$s['kw'] => 'KW ' . $s['kw'] . ' (' . number_format($s['stok']) . ' lbr)'])->all();
                })
                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                    $h = self::hargaDefault($get('id_ukuran') ? Ukuran::find($get('id_ukuran')) : null, $get('id_jenis_kayu'), $state);
                    if ($h !== null) {
                        $set('harga', $h);
                    }
                }),

            Placeholder::make('stok_saat_ini')->label('Stok Saat Ini')->content(
                fn (Get $get) => self::tampilStok(self::stokVeneerCari($get('tipe_veneer'), $get('id_ukuran'), $get('id_jenis_kayu'), $get('kw')), 'Lembar', $keluar)
            ),
            ...self::fieldsAkhir(true),
        ];
    }

    /* ───────────── BARANG UMUM ───────────── */

    public static function barangUmum(bool $keluar): array
    {
        return [
            Select::make('id_barang_umum')->label('Barang Umum')->searchable()->required()->live()
                ->options(function () use ($keluar) {
                    $q = BarangUmum::with('stok')->orderBy('nama_barang')->get();
                    if (! $keluar) {
                        return $q->pluck('nama_barang', 'id');
                    }
                    return $q->filter(fn ($b) => (float) ($b->stok?->stok_qty ?? 0) > 0)
                        ->mapWithKeys(fn ($b) => [$b->id => $b->nama_barang . ' (' . self::fmt((float) $b->stok->stok_qty) . ' ' . $b->satuan . ')']);
                })
                ->placeholder($keluar ? 'Pilih barang yang ada stoknya' : 'Pilih barang, atau ketik untuk mencari')
                // Barang masuk (BM) yang belum ada di master bisa langsung ditambahkan dari sini.
                ->createOptionForm($keluar ? null : [
                    TextInput::make('nama_barang')->label('Nama Barang')->required()->maxLength(255)
                        ->unique(table: 'barang_umum', column: 'nama_barang')
                        ->validationMessages(['unique' => 'Barang ini sudah ada di master. Cari dengan mengetik namanya.']),
                    TextInput::make('satuan')->label('Satuan')->required()->maxLength(50)
                        ->regex('/^[A-Za-z][A-Za-z.\/ ]*$/')
                        ->validationMessages(['regex' => 'Satuan harus berupa huruf, contoh: pcs, kg, liter, meter. Tidak boleh angka.'])
                        ->datalist(fn () => BarangUmum::query()->whereNotNull('satuan')->distinct()->orderBy('satuan')->pluck('satuan')->all())
                        ->helperText('Contoh: pcs, kg, liter, meter. Pilih dari yang sudah ada bila cocok.'),
                    TextInput::make('kategori')->label('Kategori (opsional)')->maxLength(255),
                ])
                ->createOptionModalHeading('Tambah Barang Umum Baru ke Master')
                ->createOptionUsing(fn (array $data) => BarangUmum::create([
                    'nama_barang' => trim($data['nama_barang']),
                    'satuan' => trim($data['satuan']),
                    'kategori' => filled($data['kategori'] ?? null) ? trim($data['kategori']) : null,
                ])->getKey()),

            Placeholder::make('stok_saat_ini')->label('Stok Saat Ini')->content(function (Get $get) use ($keluar) {
                if (! $get('id_barang_umum')) {
                    return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Pilih barang terlebih dahulu...</span>');
                }
                $b = BarangUmum::with('stok')->find($get('id_barang_umum'));
                return $b ? self::tampilStok((float) ($b->stok?->stok_qty ?? 0), e($b->satuan), $keluar)
                    : new HtmlString('<span class="text-gray-400">Barang tidak ditemukan.</span>');
            }),

            TextInput::make('jumlah')->label('Jumlah')->numeric()->minValue(0.0001)->required()
                ->maxValue(function (Get $get) use ($keluar) {
                    if (! $keluar || ! $get('id_barang_umum')) {
                        return null;
                    }
                    $b = BarangUmum::with('stok')->find($get('id_barang_umum'));
                    return $b ? ((float) ($b->stok?->stok_qty ?? 0) ?: null) : null;
                })
                ->helperText($keluar ? 'Tidak boleh melebihi stok yang tersedia.' : null),

            Textarea::make('keterangan')->label('Keterangan')->rows(3),
        ];
    }

    /* ───────────── LOG CORE ───────────── */

    private static function stokLogCoreCari($idKayu, $panjang): ?float
    {
        if (! $idKayu || $panjang === null || $panjang === '') {
            return null;
        }
        return (float) (StokLogCore::where('id_jenis_kayu', $idKayu)->where('panjang', (float) $panjang)->value('stok_qty') ?? 0);
    }

    public static function logCore(bool $keluar): array
    {
        return [
            Select::make('id_jenis_kayu')->label('Jenis Kayu')->searchable()->required()->live()
                ->options(fn () => $keluar
                    ? StokLogCore::where('stok_qty', '>', 0)->with('jenisKayu')->get()
                        ->filter(fn ($s) => $s->jenisKayu !== null)->pluck('jenisKayu.nama_kayu', 'id_jenis_kayu')->unique()
                    : JenisKayu::pluck('nama_kayu', 'id'))
                ->afterStateUpdated(fn (Set $set) => $set('panjang', null)),

            Select::make('panjang')->label('Panjang')->searchable()->required()->live()
                ->options(function (Get $get) use ($keluar) {
                    if (! $get('id_jenis_kayu')) {
                        return [];
                    }
                    if (! $keluar) {
                        return ['130' => '130 cm', '260' => '260 cm'];
                    }
                    return StokLogCore::where('id_jenis_kayu', $get('id_jenis_kayu'))->where('stok_qty', '>', 0)->get()
                        ->mapWithKeys(fn ($s) => [(string) $s->panjang => $s->panjang . ' cm (' . self::fmt($s->stok_qty) . ' batang)'])->all();
                }),

            Placeholder::make('stok_saat_ini')->label('Stok Saat Ini')->content(
                fn (Get $get) => self::tampilStok(self::stokLogCoreCari($get('id_jenis_kayu'), $get('panjang')), 'Batang', $keluar)
            ),

            TextInput::make('jumlah')->label('Jumlah (Batang)')->numeric()->minValue(1)->required()
                ->maxValue(fn (Get $get) => $keluar ? (self::stokLogCoreCari($get('id_jenis_kayu'), $get('panjang')) ?: null) : null)
                ->helperText($keluar ? 'Tidak boleh melebihi stok yang tersedia.' : null),
            Textarea::make('keterangan')->label('Keterangan')->rows(3)->required(),
        ];
    }

    /* ───────────── PLATFORM ───────────── */

    private static function stokPlatformCari(?string $tipe, $idUkuran, $idJenis, $kw): ?int
    {
        $u = $idUkuran ? Ukuran::find($idUkuran) : null;
        if (! $tipe || ! $u || ! $idJenis || ! $kw) {
            return null;
        }
        $model = $tipe === 'jadi' ? StokPlatformJadi::class : StokPlatformMth::class;
        $kolom = $tipe === 'jadi' ? 'id_jenis_barang' : 'id_jenis_kayu';
        $s = $model::where($kolom, $idJenis)->where('tebal', (float) $u->tebal)->where('kw_grade', $kw)
            ->where(fn ($q) => $q->where(fn ($x) => $x->where('panjang', $u->panjang)->where('lebar', $u->lebar))
                ->orWhere(fn ($x) => $x->where('panjang', $u->lebar)->where('lebar', $u->panjang)))->first();
        return (int) ($s->stok_lembar ?? 0);
    }

    public static function platform(bool $keluar): array
    {
        return [
            Select::make('tipe_platform')->label('Tipe Platform')->required()->live()
                ->options(['jadi' => 'Platform Jadi', 'mth' => 'Platform Mth (Setengah Jadi)']),
            Select::make('id_ukuran')->label('Ukuran')->options(fn () => Ukuran::all()->pluck('nama_ukuran', 'id'))
                ->searchable()->required()->live(),
            Select::make('id_jenis_barang')->label('Jenis Barang')->options(fn () => JenisBarang::pluck('nama_jenis_barang', 'id'))
                ->searchable()->live()
                ->visible(fn (Get $get) => $get('tipe_platform') === 'jadi')
                ->required(fn (Get $get) => $get('tipe_platform') === 'jadi'),
            Select::make('id_jenis_kayu')->label('Jenis Kayu')->options(fn () => JenisKayu::pluck('nama_kayu', 'id'))
                ->searchable()->live()
                ->visible(fn (Get $get) => $get('tipe_platform') === 'mth')
                ->required(fn (Get $get) => $get('tipe_platform') === 'mth'),
            Select::make('kw_grade')->label('KW / Grade')
                ->options(fn () => Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade'))
                ->searchable()->required()->live(),
            Placeholder::make('stok_saat_ini')->label('Stok Saat Ini')->content(function (Get $get) use ($keluar) {
                $jenis = $get('tipe_platform') === 'jadi' ? $get('id_jenis_barang') : $get('id_jenis_kayu');
                return self::tampilStok(self::stokPlatformCari($get('tipe_platform'), $get('id_ukuran'), $jenis, $get('kw_grade')), 'Lembar', $keluar);
            }),
            TextInput::make('jumlah')->label('Jumlah (Lembar)')->numeric()->minValue(1)->required()
                ->helperText($keluar ? 'Kecukupan stok dicek ulang saat nota divalidasi.' : null),
            Textarea::make('keterangan')->label('Keterangan')->rows(3)->required(),
        ];
    }

    /* ───────────── LAINNYA (manual, tanpa stok) ───────────── */

    public static function lainnya(): array
    {
        return [
            TextInput::make('nama_barang')->label('Nama Barang')->required()->maxLength(255),
            TextInput::make('jumlah')->label('Jumlah')->numeric()->minValue(0.0001)->required(),
            TextInput::make('satuan')->label('Satuan')->maxLength(50),
            Textarea::make('keterangan')->label('Keterangan')->rows(3),
        ];
    }
}
