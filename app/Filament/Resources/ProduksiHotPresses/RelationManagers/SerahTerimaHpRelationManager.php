<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Models\JenisKayu;
use App\Models\PlatformHasilHp;
use App\Models\ProduksiGrajitriplek;
use App\Models\ProduksiHp;
use App\Models\ProduksiSanding;
use App\Models\SerahTerimaHp;
use App\Models\TriplekHasilHp;
use App\Services\StokPlatformMthService;
use App\Services\StokTriplekMthService;
use App\Services\TerimaTriplekJadiService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerahTerimaHpRelationManager extends RelationManager
{
    private const ROLE_ADMIN = ['super_admin', 'Super Admin', 'admin_kayu'];

    /**
     * Mapping dari `tipe` (tab yang sedang dibuka) ke value kolom `tujuan`.
     * 'hp' tidak dipetakan karena tab HP adalah sumber, bukan tujuan.
     */
    private const TIPE_TO_TUJUAN = [
        'graji' => 'graji_triplek',
        'sanding' => 'sanding',
    ];

    protected static string $relationship = 'serahTerimaHp';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return match (get_class($ownerRecord)) {
            ProduksiHp::class => 'Serah Hasil Produksi',
            ProduksiGrajitriplek::class => 'Terima Triplek',
            ProduksiSanding::class => 'Terima Platform/Plywood',
            default => 'Serah Terima',
        };
    }

    protected function getTipe(): string
    {
        return match (get_class($this->getOwnerRecord())) {
            ProduksiHp::class => 'hp',
            ProduksiGrajitriplek::class => 'graji',
            ProduksiSanding::class => 'sanding',
            default => 'unknown',
        };
    }

    /**
     * Apakah record ini berasal dari Gudang Triplek Jadi?
     */
    protected function isDariTriplekJadi($record): bool
    {
        return $record->id_triplek_mutasi_keluar !== null;
    }

    /**
     * Apakah record ini berasal dari Gudang Platform Mentah?
     */
    protected function isDariPlatformMth($record): bool
    {
        return $record->id_platform_mth_mutasi_keluar !== null;
    }

    /**
     * Apakah record ini berasal dari Gudang Triplek Mentah?
     */
    protected function isDariTriplekMth($record): bool
    {
        return $record->id_triplek_mth_mutasi_keluar !== null;
    }

    /**
     * Kategori barang: PLYWOOD / PLATFORM.
     *
     * Diambil dari master grade -> kategoriBarang. Untuk barang asal Gudang
     * Triplek Jadi / Gudang Triplek Mentah, mutasi keluar tidak menyimpan
     * kategori sendiri — isinya selalu Plywood, jadi di-hardcode. Untuk
     * Gudang Platform Mentah, isinya selalu Platform.
     */
    protected function kategoriBarang($record): string
    {
        if ($this->isDariTriplekJadi($record) || $this->isDariTriplekMth($record)) {
            return 'Plywood';
        }

        if ($this->isDariPlatformMth($record)) {
            return 'Platform';
        }

        return $record->barangSetengahJadi?->grade?->kategoriBarang?->nama_kategori ?? '-';
    }

    /**
     * Ambil data ringkas dari record untuk ditampilkan di preview modal terima.
     * Mendukung semua sumber lama (triplek HP, platform HP, hasil Graji, hasil
     * Sanding) lewat accessor model, PLUS sumber baru: Gudang Triplek Jadi,
     * Gudang Platform Mentah, dan Gudang Triplek Mentah.
     */
    protected function getPreviewData($record): array
    {
        // ── Asal: Gudang Triplek Jadi ──
        if ($this->isDariTriplekJadi($record)) {
            $m = $record->triplekMutasiKeluar;

            return [
                'no_palet' => $m ? ($m->jumlah_palet.' palet') : '-',
                'kategori' => 'Plywood',
                'jenis_barang' => $m?->jenisKayu?->nama_kayu ?? '-',
                'grade' => $m?->kw_grade ?? '-',
                'ukuran' => $m
                    ? ($m->panjang + 0).'×'.($m->lebar + 0).'×'.($m->tebal + 0)
                    : '-',
                'isi' => $m?->stok_lembar ?? '-',
                'dari_mesin' => '-',
                'asal' => 'Gudang Triplek Jadi',
            ];
        }

        // ── Asal: Gudang Platform Mentah ──
        if ($this->isDariPlatformMth($record)) {
            $m = $record->platformMthMutasiKeluar;

            return [
                'no_palet' => '-',
                'kategori' => 'Platform',
                'jenis_barang' => $m?->jenisKayu?->nama_kayu ?? '-',
                'grade' => $m?->kw_grade ?? '-',
                'ukuran' => $m
                    ? ($m->panjang + 0).'×'.($m->lebar + 0).'×'.($m->tebal + 0)
                    : '-',
                'isi' => $m?->stok_lembar ?? '-',
                'dari_mesin' => '-',
                'asal' => 'Gudang Platform Mentah',
            ];
        }

        // ── Asal: Gudang Triplek Mentah ──
        if ($this->isDariTriplekMth($record)) {
            $m = $record->triplekMthMutasiKeluar;

            return [
                'no_palet' => '-',
                'kategori' => 'Plywood',
                'jenis_barang' => $m?->jenisKayu?->nama_kayu ?? '-',
                'grade' => $m?->kw_grade ?? '-',
                'ukuran' => $m
                    ? ($m->panjang + 0).'×'.($m->lebar + 0).'×'.($m->tebal + 0)
                    : '-',
                'isi' => $m?->stok_lembar ?? '-',
                'dari_mesin' => '-',
                'asal' => 'Gudang Triplek Mentah',
            ];
        }

        // ── Asal lama (logika asli, tidak diubah) ──
        $hasil = $record->hasil;
        $bsj = $record->barangSetengahJadi;

        return [
            'no_palet' => $hasil?->no_palet ?? '-',
            'kategori' => $bsj?->grade?->kategoriBarang?->nama_kategori ?? '-',
            'jenis_barang' => $bsj?->jenisBarang?->nama_jenis_barang ?? '-',
            'grade' => $bsj?->grade?->nama_grade ?? '-',
            'ukuran' => $bsj?->ukuran?->nama_ukuran ?? '-',
            'isi' => $record->jumlah ?? '-',
            'dari_mesin' => $hasil?->mesin?->nama_mesin ?? '-',
            'asal' => $record->asalLabel,
        ];
    }

    public function table(Table $table): Table
    {
        $tipe = $this->getTipe();
        $ownerId = $this->getOwnerRecord()->id;

        $eagerLoads = [
            'triplekHasilHp.mesin',
            'triplekHasilHp.barangSetengahJadi.jenisBarang',
            'triplekHasilHp.barangSetengahJadi.grade.kategoriBarang',
            'triplekHasilHp.barangSetengahJadi.ukuran',
            'platformHasilHp.mesin',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'platformHasilHp.barangSetengahJadi.grade.kategoriBarang',
            'platformHasilHp.barangSetengahJadi.ukuran',
            'hasilGrajiTriplek.barangSetengahJadiHp.jenisBarang',
            'hasilGrajiTriplek.barangSetengahJadiHp.grade.kategoriBarang',
            'hasilGrajiTriplek.barangSetengahJadiHp.ukuran',
            'hasilSanding.mesin',
            'hasilSanding.barangSetengahJadi.jenisBarang',
            'hasilSanding.barangSetengahJadi.grade.kategoriBarang',
            'hasilSanding.barangSetengahJadi.ukuran',
            'triplekMutasiKeluar.jenisKayu',
            'platformMthMutasiKeluar.jenisKayu',
            'triplekMthMutasiKeluar.jenisKayu',
        ];

        return $table
            ->modifyQueryUsing(function ($query) use ($tipe, $ownerId, $eagerLoads) {
                // Reset constraint bawaan dari relasi dasar (tanpa mengganti objek $query,
                // karena Filament butuh instance query yang sama untuk proses selanjutnya)
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                $query->with($eagerLoads);

                if ($tipe === 'hp') {
                    $triplekIds = TriplekHasilHp::where('id_produksi_hp', $ownerId)->pluck('id');
                    $platformIds = PlatformHasilHp::where('id_produksi_hp', $ownerId)->pluck('id');

                    return $query
                        // 🌟 Barang yang sudah ditolak (di tujuan manapun) tidak lagi
                        // ditampilkan, termasuk di tab HP (sisi penyerah).
                        ->whereNull('ditolak_oleh')
                        ->where(function ($q) use ($triplekIds, $platformIds) {
                            $q->whereIn('id_triplek_hasil_hp', $triplekIds)
                                ->orWhereIn('id_platform_hasil_hp', $platformIds);
                        })
                        ->orderBy('created_at', 'desc');
                }

                if ($tipe === 'graji') {
                    // Menuju Graji Triplek: cukup filter langsung dari kolom `tujuan`
                    // (mencakup dari hotpress via id_triplek_hasil_hp, serah manual dari
                    // Sanding, ATAU dari Gudang Triplek Mentah).
                    return $query
                        ->where('tujuan', self::TIPE_TO_TUJUAN['graji'])
                        // 🌟 Barang yang sudah ditolak tidak muncul lagi di sini.
                        ->whereNull('ditolak_oleh')
                        ->where(function ($q) use ($ownerId) {
                            $q->where('diterima_oleh', '-')
                                ->orWhere('id_produksi_graji_triplek', $ownerId);
                        })
                        ->orderBy('diterima_oleh', 'asc')
                        ->orderBy('created_at', 'desc');
                }

                if ($tipe === 'sanding') {
                    // Menuju Sanding: filter langsung dari kolom `tujuan`.
                    // Baris dari Gudang Triplek Jadi & Gudang Platform Mentah
                    // juga bertujuan 'sanding', jadi otomatis ikut tampil di sini
                    // tanpa syarat tambahan.
                    return $query
                        ->where('tujuan', self::TIPE_TO_TUJUAN['sanding'])
                        // 🌟 Barang yang sudah ditolak tidak muncul lagi di sini.
                        ->whereNull('ditolak_oleh')
                        ->where(function ($q) use ($ownerId) {
                            $q->where('diterima_oleh', '-')
                                ->orWhere('id_produksi_sanding', $ownerId);
                        })
                        ->orderBy('diterima_oleh', 'asc')
                        ->orderBy('created_at', 'desc');
                }

                return $query;
            })
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->state(function ($record) {
                        if ($this->isDariTriplekJadi($record)) {
                            return ($record->triplekMutasiKeluar?->jumlah_palet ?? '-').' palet';
                        }

                        if ($this->isDariPlatformMth($record) || $this->isDariTriplekMth($record)) {
                            return '-';
                        }

                        return $record->hasil?->no_palet ?? '-';
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('asal_label')
                    ->label('Asal')
                    ->state(function ($record) {
                        if ($this->isDariTriplekJadi($record)) {
                            return 'Gudang Triplek Jadi';
                        }

                        if ($this->isDariPlatformMth($record)) {
                            return 'Gudang Platform Mentah';
                        }

                        if ($this->isDariTriplekMth($record)) {
                            return 'Gudang Triplek Mentah';
                        }

                        return $record->asalLabel;
                    })
                    ->badge()
                    ->color(function ($record) {
                        if ($this->isDariTriplekJadi($record) || $this->isDariPlatformMth($record) || $this->isDariTriplekMth($record)) {
                            return 'success';
                        }

                        return match ($record->asalLabel) {
                            'Hotpress' => 'info',
                            'Graji Triplek' => 'warning',
                            'Sanding' => 'purple',
                            default => 'gray',
                        };
                    }),

                // 🌟 KATEGORI: Plywood / Platform
                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->state(fn ($record) => $this->kategoriBarang($record))
                    ->badge()
                    ->color(fn ($state) => match (strtoupper((string) $state)) {
                        'PLYWOOD' => 'success',
                        'PLATFORM' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('mesin')
                    ->label('Mesin')
                    ->state(fn ($record) => $record->hasil?->mesin?->nama_mesin ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('jenis_barang')
                    ->label('Jenis Barang')
                    ->state(function ($record) {
                        if ($this->isDariTriplekJadi($record)) {
                            return $record->triplekMutasiKeluar?->jenisKayu?->nama_kayu ?? '-';
                        }

                        if ($this->isDariPlatformMth($record)) {
                            return $record->platformMthMutasiKeluar?->jenisKayu?->nama_kayu ?? '-';
                        }

                        if ($this->isDariTriplekMth($record)) {
                            return $record->triplekMthMutasiKeluar?->jenisKayu?->nama_kayu ?? '-';
                        }

                        return $record->barangSetengahJadi?->jenisBarang?->nama_jenis_barang ?? '-';
                    }),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->state(function ($record) {
                        if ($this->isDariTriplekJadi($record)) {
                            return $record->triplekMutasiKeluar?->kw_grade ?? '-';
                        }

                        if ($this->isDariPlatformMth($record)) {
                            return $record->platformMthMutasiKeluar?->kw_grade ?? '-';
                        }

                        if ($this->isDariTriplekMth($record)) {
                            return $record->triplekMthMutasiKeluar?->kw_grade ?? '-';
                        }

                        return $record->barangSetengahJadi?->grade?->nama_grade ?? '-';
                    }),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(function ($record) {
                        if ($this->isDariTriplekJadi($record)) {
                            $m = $record->triplekMutasiKeluar;

                            return $m
                                ? ($m->panjang + 0).'×'.($m->lebar + 0).'×'.($m->tebal + 0)
                                : '-';
                        }

                        if ($this->isDariPlatformMth($record)) {
                            $m = $record->platformMthMutasiKeluar;

                            return $m
                                ? ($m->panjang + 0).'×'.($m->lebar + 0).'×'.($m->tebal + 0)
                                : '-';
                        }

                        if ($this->isDariTriplekMth($record)) {
                            $m = $record->triplekMthMutasiKeluar;

                            return $m
                                ? ($m->panjang + 0).'×'.($m->lebar + 0).'×'.($m->tebal + 0)
                                : '-';
                        }

                        return $record->barangSetengahJadi?->ukuran?->nama_ukuran ?? '-';
                    }),

                TextColumn::make('isi')
                    ->label('Jumlah Lembar')
                    ->state(function ($record) {
                        if ($this->isDariTriplekJadi($record)) {
                            return $record->triplekMutasiKeluar?->stok_lembar ?? '-';
                        }

                        if ($this->isDariPlatformMth($record)) {
                            return $record->platformMthMutasiKeluar?->stok_lembar ?? '-';
                        }

                        if ($this->isDariTriplekMth($record)) {
                            return $record->triplekMthMutasiKeluar?->stok_lembar ?? '-';
                        }

                        return $record->jumlah ?? '-';
                    })
                    ->alignCenter(),

                TextColumn::make('diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('diterima_oleh')
                    ->label('Diterima Oleh')
                    ->badge()
                    ->color(fn ($record, $state) => $record->isDitolak() ? 'danger' : ($state === '-' ? 'gray' : 'success'))
                    ->formatStateUsing(function ($record, $state) {
                        if ($record->isDitolak()) {
                            return 'Ditolak';
                        }

                        return $state === '-' ? 'Menunggu' : $state;
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn ($state) => match ($state) {
                        'Terima Triplek', 'Terima Platform', 'Terima dari Triplek Jadi',
                        'Terima dari Gudang Platform Mentah', 'Terima dari Gudang Triplek Mentah' => 'success',
                        'Serah Triplek', 'Serah Platform', 'Serah ke Sanding', 'Serah ke Graji' => 'warning',
                        'Ditolak' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])

            ->actions([
                Action::make('terima')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Terima barang ini?')
                    ->modalDescription('Periksa data berikut sebelum menerima.')
                    ->schema(function ($record) {
                        $preview = $this->getPreviewData($record);

                        return [
                            Grid::make(2)
                                ->schema([
                                    Placeholder::make('preview_no_palet')
                                        ->label('No. Palet')
                                        ->content($preview['no_palet']),

                                    Placeholder::make('preview_asal')
                                        ->label('Asal')
                                        ->content($preview['asal']),

                                    Placeholder::make('preview_kategori')
                                        ->label('Kategori')
                                        ->content($preview['kategori']),

                                    Placeholder::make('preview_jenis_barang')
                                        ->label('Jenis Barang')
                                        ->content($preview['jenis_barang']),

                                    Placeholder::make('preview_grade')
                                        ->label('Grade')
                                        ->content($preview['grade']),

                                    Placeholder::make('preview_ukuran')
                                        ->label('Ukuran')
                                        ->content($preview['ukuran']),

                                    Placeholder::make('preview_isi')
                                        ->label('Jumlah Lembar')
                                        ->content($preview['isi']),

                                    Placeholder::make('preview_dari_mesin')
                                        ->label('Dari Mesin')
                                        ->content($preview['dari_mesin']),
                                ]),
                        ];
                    })
                    // Muncul kalau tujuannya sesuai dengan tab yang sedang dibuka, dan belum diterima/ditolak.
                    ->visible(function ($record) use ($tipe) {
                        if ($record->diterima_oleh !== '-' || $record->isDitolak()) {
                            return false;
                        }

                        return $record->tujuan === (self::TIPE_TO_TUJUAN[$tipe] ?? null);
                    })
                    ->action(function ($record) use ($ownerId, $tipe) {
                        $stokTriplekService = app(StokTriplekMthService::class);
                        $stokPlatformService = app(StokPlatformMthService::class);

                        try {
                            DB::transaction(function () use ($record, $ownerId, $tipe, $stokTriplekService, $stokPlatformService) {
                                $fresh = SerahTerimaHp::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-' || $fresh->ditolak_oleh) {
                                    throw new \RuntimeException('Barang ini sudah diambil produksi lain atau sudah ditolak.');
                                }

                                if ($tipe === 'graji') {
                                    $fresh->update([
                                        'diterima_oleh' => Auth::user()->name.' - Graji Triplek',
                                        'id_produksi_graji_triplek' => $ownerId,
                                        // 🌟 `tujuan` disamakan dengan tempat aktual barang
                                        // diterima (self-healing), bukan sekadar percaya nilai
                                        // yang di-set saat baris ini dibuat.
                                        'tujuan' => self::TIPE_TO_TUJUAN['graji'],
                                        'status' => $fresh->id_triplek_hasil_hp
                                            ? 'Terima Triplek'
                                            : ($fresh->id_triplek_mth_mutasi_keluar
                                                ? 'Terima dari Gudang Triplek Mentah'
                                                : 'Terima dari Sanding'),
                                    ]);

                                    // Stok triplek BERTAMBAH kalau barang berasal dari hotpress.
                                    if ($fresh->id_triplek_hasil_hp) {
                                        $this->prosesTerimaTriplek($fresh, $stokTriplekService);
                                    }
                                    // 🚫 DINONAKTIFKAN SEMENTARA (atas permintaan): pengurangan
                                    // stok Triplek Mentah saat diterima dari Gudang Triplek
                                    // Mentah TIDAK dibutuhkan untuk saat ini. Barang tetap bisa
                                    // "Diterima" seperti biasa (status/diterima_oleh tetap
                                    // ter-update di atas), hanya saja baris stok Triplek Mentah
                                    // TIDAK dipotong otomatis lagi.
                                    // Untuk mengaktifkan kembali, un-comment blok di bawah ini:
                                    // elseif ($fresh->id_triplek_mth_mutasi_keluar) {
                                    //     // 🌟 Dari GUDANG TRIPLEK MENTAH: potong stok triplek
                                    //     // mentah, tulis log 'keluar', mutasi sudah ditandai
                                    //     // diterima di atas.
                                    //     $this->prosesKeluarTriplekMth($fresh, $stokTriplekService);
                                    // }

                                    return;
                                }

                                if ($tipe === 'sanding') {
                                    $fresh->update([
                                        'diterima_oleh' => Auth::user()->name.' - Sanding',
                                        'id_produksi_sanding' => $ownerId,
                                        // 🌟 `tujuan` disamakan dengan tempat aktual barang
                                        // diterima (self-healing), bukan sekadar percaya nilai
                                        // yang di-set saat baris ini dibuat.
                                        'tujuan' => self::TIPE_TO_TUJUAN['sanding'],
                                        'status' => $fresh->id_platform_hasil_hp
                                            ? 'Terima Platform'
                                            : ($fresh->id_triplek_mutasi_keluar
                                                ? 'Terima dari Triplek Jadi'
                                                : ($fresh->id_platform_mth_mutasi_keluar
                                                    ? 'Terima dari Gudang Platform Mentah'
                                                    : 'Terima dari Graji')),
                                    ]);

                                    if ($fresh->id_platform_hasil_hp) {
                                        // Dari hotpress: stok platform mentah bertambah (logika lama).
                                        $this->prosesTerimaPlatform($fresh, $stokPlatformService);
                                    } elseif ($fresh->id_triplek_mutasi_keluar) {
                                        // Dari GUDANG TRIPLEK JADI: potong stok triplek jadi +
                                        // tulis HppTriplekJadiLog 'keluar' + tandai mutasi diterima.
                                        // Sanding adalah tujuan produksi, jadi TIDAK menambah stok
                                        // apa pun di sini (tambahStokGudangSatu: false).
                                        app(TerimaTriplekJadiService::class)
                                            ->konfirmasi($fresh, tambahStokGudangSatu: false);
                                    }
                                    // 🚫 DINONAKTIFKAN SEMENTARA (atas permintaan): pengurangan
                                    // stok Platform Mentah saat diterima dari Gudang Platform
                                    // Mentah TIDAK dibutuhkan untuk saat ini. Barang tetap bisa
                                    // "Diterima" seperti biasa (status/diterima_oleh tetap
                                    // ter-update di atas), hanya saja baris stok Platform Mentah
                                    // TIDAK dipotong otomatis lagi.
                                    // Untuk mengaktifkan kembali, un-comment blok di bawah ini:
                                    // elseif ($fresh->id_platform_mth_mutasi_keluar) {
                                    //     // 🌟 Dari GUDANG PLATFORM MENTAH: potong stok platform
                                    //     // mentah, tulis log 'keluar', mutasi sudah ditandai
                                    //     // diterima di atas.
                                    //     $this->prosesKeluarPlatformMth($fresh, $stokPlatformService);
                                    // }
                                    // Serah manual dari Graji -> Sanding: tetap tanpa efek stok.
                                }
                            });

                            Notification::make()
                                ->title('Barang Berhasil Diterima')
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // 🌟 Action baru: TOLAK
                //
                // Dipakai kalau barang yang di-serah ternyata salah kirim / salah
                // catat dari gudang asal. Karena stok baru bertambah pada saat
                // action `terima` dipanggil, dan `tolak` hanya boleh muncul saat
                // barang BELUM diterima, maka `tolak` tidak pernah perlu menyentuh
                // service stok apa pun — cukup menandai record supaya tidak lagi
                // ikut ke query manapun (lihat `whereNull('ditolak_oleh')` di
                // `modifyQueryUsing` atas).
                Action::make('tolak')
                    ->label('Tolak')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Tolak barang ini?')
                    ->modalDescription('Barang akan hilang dari daftar Serah Terima dan TIDAK memotong / menambah stok apa pun di gudang manapun.')
                    ->schema([
                        Textarea::make('alasan_tolak')
                            ->label('Alasan Penolakan')
                            ->placeholder('Contoh: jumlah lembar tidak sesuai fisik / salah kirim dari gudang')
                            ->required()
                            ->rows(3),
                    ])
                    // Muncul di kondisi yang sama dengan 'terima': belum diterima,
                    // belum ditolak, dan tujuannya sesuai tab yang sedang dibuka.
                    ->visible(function ($record) use ($tipe) {
                        if ($record->diterima_oleh !== '-' || $record->isDitolak()) {
                            return false;
                        }

                        return $record->tujuan === (self::TIPE_TO_TUJUAN[$tipe] ?? null);
                    })
                    ->action(function ($record, array $data) {
                        try {
                            DB::transaction(function () use ($record, $data) {
                                $fresh = SerahTerimaHp::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-' || $fresh->ditolak_oleh) {
                                    throw new \RuntimeException('Barang ini sudah diproses (diterima/ditolak) sebelumnya.');
                                }

                                $fresh->update([
                                    'ditolak_oleh' => Auth::user()->name,
                                    'alasan_tolak' => $data['alasan_tolak'],
                                    'ditolak_at' => now(),
                                    'status' => 'Ditolak',
                                ]);
                            });

                            Notification::make()
                                ->title('Barang Ditolak')
                                ->body('Item tidak akan muncul lagi di daftar Serah Terima dan stok gudang asal tidak berubah.')
                                ->warning()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->hasAnyRole(self::ROLE_ADMIN)),
                ]),
            ]);
    }

    /**
     * Resolve data dari hasil triplek HP, lalu delegasikan penambahan stok
     * ke StokTriplekMthService. HPP belum dihitung (0 dulu, menyusul).
     */
    protected function prosesTerimaTriplek(SerahTerimaHp $serahTerima, StokTriplekMthService $service): void
    {
        $hasil = $serahTerima->triplekHasilHp()
            ->with('barangSetengahJadi.ukuran', 'barangSetengahJadi.grade', 'barangSetengahJadi.jenisBarang')
            ->first();

        if (! $hasil || ! $hasil->barangSetengahJadi) {
            throw new \RuntimeException('Data barang setengah jadi tidak ditemukan.');
        }

        $ukuran = $hasil->barangSetengahJadi->ukuran;
        $grade = $hasil->barangSetengahJadi->grade;
        $jenisBarang = $hasil->barangSetengahJadi->jenisBarang;

        if (! $ukuran || ! $grade || ! $jenisBarang) {
            throw new \RuntimeException('Data ukuran, grade, atau jenis barang tidak lengkap.');
        }

        // "Jenis Barang" pada hasil triplek sebenarnya merepresentasikan jenis kayu,
        // tapi disimpan lewat tabel jenis_barang (bukan jenis_kayus) — dicocokkan by nama.
        $jenisKayu = JenisKayu::where('nama_kayu', $jenisBarang->nama_jenis_barang)->first();

        if (! $jenisKayu) {
            throw new \RuntimeException("Jenis kayu \"{$jenisBarang->nama_jenis_barang}\" tidak ditemukan di data Jenis Kayu. Mohon samakan penamaan atau tambahkan datanya terlebih dahulu.");
        }

        $lembar = (float) $hasil->isi;
        $kubikasi = $lembar * (float) $ukuran->kubikasi / 10000000;

        $service->tambah(
            idJenisKayu: $jenisKayu->id,
            panjang: $ukuran->panjang,
            lebar: $ukuran->lebar,
            tebal: $ukuran->tebal,
            kwGrade: $grade->nama_grade,
            lembar: $lembar,
            kubikasi: $kubikasi,
            keterangan: 'Masuk dari Graji — terima triplek dari hotpress (via serah terima #'.$serahTerima->id.')',
            referensi: $serahTerima,
        );
    }

    /**
     * Resolve data dari hasil platform HP, lalu delegasikan penambahan stok
     * ke StokPlatformMthService. HPP belum dihitung (0 dulu, menyusul).
     */
    protected function prosesTerimaPlatform(SerahTerimaHp $serahTerima, StokPlatformMthService $service): void
    {
        $hasil = $serahTerima->platformHasilHp()
            ->with('barangSetengahJadi.ukuran', 'barangSetengahJadi.grade', 'barangSetengahJadi.jenisBarang')
            ->first();

        if (! $hasil || ! $hasil->barangSetengahJadi) {
            throw new \RuntimeException('Data barang setengah jadi tidak ditemukan.');
        }

        $ukuran = $hasil->barangSetengahJadi->ukuran;
        $grade = $hasil->barangSetengahJadi->grade;
        $jenisBarang = $hasil->barangSetengahJadi->jenisBarang;

        if (! $ukuran || ! $grade || ! $jenisBarang) {
            throw new \RuntimeException('Data ukuran, grade, atau jenis barang tidak lengkap.');
        }

        // "Jenis Barang" pada hasil platform sebenarnya merepresentasikan jenis kayu,
        // tapi disimpan lewat tabel jenis_barang (bukan jenis_kayus) — dicocokkan by nama.
        $jenisKayu = JenisKayu::where('nama_kayu', $jenisBarang->nama_jenis_barang)->first();

        if (! $jenisKayu) {
            throw new \RuntimeException("Jenis kayu \"{$jenisBarang->nama_jenis_barang}\" tidak ditemukan di data Jenis Kayu. Mohon samakan penamaan atau tambahkan datanya terlebih dahulu.");
        }

        $lembar = (float) $hasil->isi;
        $kubikasi = $lembar * (float) $ukuran->kubikasi / 10000000;

        $service->tambah(
            idJenisKayu: $jenisKayu->id,
            panjang: $ukuran->panjang,
            lebar: $ukuran->lebar,
            tebal: $ukuran->tebal,
            kwGrade: $grade->nama_grade,
            lembar: $lembar,
            kubikasi: $kubikasi,
            keterangan: 'Masuk dari Sanding — terima platform dari hotpress (via serah terima #'.$serahTerima->id.')',
            referensi: $serahTerima,
        );
    }

    /**
     * 🚫 SEMENTARA TIDAK DIPANGGIL (lihat catatan di action 'terima' untuk tipe
     * 'sanding'). Method ini dibiarkan utuh supaya gampang diaktifkan kembali —
     * cukup un-comment pemanggilannya di atas.
     *
     * Barang berasal dari Gudang Platform Mentah (mutasi keluar manual menuju
     * Sanding). Potong stok Platform Mentah sesuai kuantitas yang tercatat
     * di mutasi keluar tersebut. HPP belum dihitung (mengikuti hpp_average
     * berjalan, lewat StokPlatformMthService::kurang()).
     */
    protected function prosesKeluarPlatformMth(SerahTerimaHp $serahTerima, StokPlatformMthService $service): void
    {
        $mutasi = $serahTerima->platformMthMutasiKeluar;

        if (! $mutasi) {
            throw new \RuntimeException('Data mutasi keluar Gudang Platform Mentah tidak ditemukan.');
        }

        $service->kurang(
            idJenisKayu: $mutasi->id_jenis_kayu,
            panjang: $mutasi->panjang,
            lebar: $mutasi->lebar,
            tebal: $mutasi->tebal,
            kwGrade: (string) $mutasi->kw_grade,
            lembar: (float) $mutasi->stok_lembar,
            kubikasi: (float) $mutasi->stok_kubikasi,
            keterangan: 'Keluar ke Sanding — diterima dari Gudang Platform Mentah (via serah terima #'.$serahTerima->id.')',
            referensi: $serahTerima,
        );
    }

    /**
     * 🚫 SEMENTARA TIDAK DIPANGGIL (lihat catatan di action 'terima' untuk tipe
     * 'graji'). Method ini dibiarkan utuh supaya gampang diaktifkan kembali —
     * cukup un-comment pemanggilannya di atas.
     *
     * Barang berasal dari Gudang Triplek Mentah (mutasi keluar manual menuju
     * Graji Triplek). Potong stok Triplek Mentah sesuai kuantitas yang
     * tercatat di mutasi keluar tersebut.
     */
    protected function prosesKeluarTriplekMth(SerahTerimaHp $serahTerima, StokTriplekMthService $service): void
    {
        $mutasi = $serahTerima->triplekMthMutasiKeluar;

        if (! $mutasi) {
            throw new \RuntimeException('Data mutasi keluar Gudang Triplek Mentah tidak ditemukan.');
        }

        $service->kurang(
            idJenisKayu: $mutasi->id_jenis_kayu,
            panjang: $mutasi->panjang,
            lebar: $mutasi->lebar,
            tebal: $mutasi->tebal,
            kwGrade: (string) $mutasi->kw_grade,
            lembar: (float) $mutasi->stok_lembar,
            kubikasi: (float) $mutasi->stok_kubikasi,
            keterangan: 'Keluar ke Graji Triplek — diterima dari Gudang Triplek Mentah (via serah terima #'.$serahTerima->id.')',
            referensi: $serahTerima,
        );
    }
}