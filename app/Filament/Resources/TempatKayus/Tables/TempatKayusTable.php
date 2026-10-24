<?php

namespace App\Filament\Resources\TempatKayus\Tables;

use App\Models\DetailTurusanKayu;
use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use App\Models\KayuMasuk;
use App\Models\Lahan;
use App\Models\NotaKayu;
use App\Models\TempatKayu;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TempatKayusTable
{
    private const ROLE_GRADER = ['Grader Kayu 1', 'Grader Kayu 2'];

    private const ROLE_PENGAWAS = ['pengawas_rotary_1', 'pengawas_rotary_2'];

    private const ROLE_ADMIN = ['super_admin', 'Super Admin', 'admin_kayu'];

    public const MESIN_MAP = [
        130 => ['SANJI', 'YUEQUN'],
        260 => ['SPINDLESS', 'MERANTI'],
    ];

    /** @var array<int, Collection> */
    private static array $snapshot = [];

    /** Penanda agar auto-sync hanya jalan sekali per request. */
    private static bool $autoSyncSudahJalan = false;

    /**
     * Sinkronisasi semua lahan baru: buat HppAverageSummarie & TempatKayu
     * untuk lahan yang belum punya. Idempotent (aman dipanggil berulang).
     *
     * @return array{summary: int, tempat_kayu: int}
     */
    public static function sinkronisasiLahanBaru(): array
    {
        $lahanIdsTanpaSummary = Lahan::whereHas('detailTurusanKayus')
            ->whereDoesntHave('summaries')
            ->pluck('id');

        $lahanIdsTanpaTempatKayu = Lahan::whereHas('detailTurusanKayus')
            ->whereDoesntHave('tempatKayu')
            ->pluck('id');

        $semuaLahanIds = $lahanIdsTanpaSummary
            ->merge($lahanIdsTanpaTempatKayu)
            ->unique()
            ->values();

        // Early exit: kasus paling umum, hanya 2 query ringan.
        if ($semuaLahanIds->isEmpty()) {
            return ['summary' => 0, 'tempat_kayu' => 0];
        }

        $summaryCount = 0;
        $tempatKayuCount = 0;

        DB::transaction(function () use ($lahanIdsTanpaSummary, $semuaLahanIds, &$summaryCount, &$tempatKayuCount) {
            // ── Summaries ────────────────────────────────────────────────
            if ($lahanIdsTanpaSummary->isNotEmpty()) {
                $details = DetailTurusanKayu::whereIn('lahan_id', $lahanIdsTanpaSummary)->get();

                $grouped = $details->groupBy(
                    fn ($d) => "{$d->lahan_id}_{$d->jenis_kayu_id}_{$d->panjang}"
                );

                foreach ($grouped as $rows) {
                    HppAverageSummarie::firstOrCreate([
                        'id_lahan' => (int) $rows->first()->lahan_id,
                        'id_jenis_kayu' => (int) $rows->first()->jenis_kayu_id,
                        'panjang' => (int) $rows->first()->panjang,
                        'grade' => null,
                    ], [
                        'stok_batang' => 0,
                        'stok_kubikasi' => 0.0,
                        'nilai_stok' => 0.0,
                        'hpp_average' => 0.0,
                    ]);
                    $summaryCount++;
                }
            }

            // ── Tempat Kayu ──────────────────────────────────────────────
            foreach ($semuaLahanIds as $lahanId) {
                if (TempatKayu::where('id_lahan', $lahanId)->exists()) {
                    continue;
                }

                $kayuMasuk = KayuMasuk::whereHas('detailTurusanKayus', function ($q) use ($lahanId) {
                    $q->where('lahan_id', $lahanId);
                })->latest()->first();

                if ($kayuMasuk) {
                    // firstOrCreate untuk mengurangi risiko duplikat jika 2 user membuka bersamaan
                    TempatKayu::firstOrCreate(
                        ['id_lahan' => $lahanId],
                        [
                            'id_kayu_masuk' => $kayuMasuk->id,
                            'jumlah_batang' => 0,
                        ]
                    );
                    $tempatKayuCount++;
                }
            }
        });

        return ['summary' => $summaryCount, 'tempat_kayu' => $tempatKayuCount];
    }

    /**
     * Jalankan sinkronisasi otomatis (maksimal sekali per request).
     * Kegagalan sinkronisasi tidak menghentikan halaman.
     */
    private static function autoSinkronisasi(): void
    {
        if (self::$autoSyncSudahJalan) {
            return;
        }

        self::$autoSyncSudahJalan = true;

        try {
            $hasil = self::sinkronisasiLahanBaru();

            Log::channel('single')->info('Auto Sync Lahan Baru jalan', $hasil);

            if ($hasil['summary'] > 0 || $hasil['tempat_kayu'] > 0) {
                self::forgetSnapshot(); // data berubah, buang cache snapshot

                Notification::make()
                    ->title('Sinkronisasi otomatis berhasil')
                    ->body("Summary dibuat: {$hasil['summary']} | Tempat Kayu dibuat: {$hasil['tempat_kayu']} lahan")
                    ->success()
                    ->send();
            } else {
                // Tetap beri status agar jelas sinkronisasi sudah berjalan
                Notification::make()
                    ->title('Sinkronisasi otomatis selesai')
                    ->body('Tidak ada lahan baru yang perlu disinkronkan.')
                    ->info()
                    ->send();
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('Auto Sync Lahan Baru FAILED', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Sinkronisasi otomatis gagal')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * ✅ DISESUAIKAN AGAR SAMA PERSIS DENGAN LOGIKA `php artisan test3 {id}`
     * (App\Console\Commands\Test3Command).
     *
     * 1) Cutoff dihitung dengan 3 skenario fallback yang sama seperti test3.
     * 2) Filter DetailTurusanKayu memakai kombinasi status pelunasan:
     *      - Belum lunas -> aktif jika created_at nota > cutoff
     *      - Sudah lunas -> aktif jika updated_at nota > cutoff
     * 3) Opname SETELAH reset ikut dihitung sebagai baris 'OPNAME' dengan
     *    delta yang BISA MINUS.
     */
    public static function getKayuAktif(int $lahanId): Collection
    {
        if (isset(self::$snapshot[$lahanId])) {
            return self::$snapshot[$lahanId];
        }

        // 1) Cari reset terakhir (log 'keluar' dengan stok_batang_after = 0)
        $lastReset = HppAverageLog::where('id_lahan', $lahanId)
            ->where('tipe_transaksi', 'keluar')
            ->where('stok_batang_after', 0)
            ->orderByDesc('id')
            ->first();

        $cutoff = null;

        if ($lastReset) {
            // 2) Cari log 'masuk' terakhir SEBELUM reset
            $lastNotaBeforeReset = HppAverageLog::where('id_lahan', $lahanId)
                ->where('tipe_transaksi', 'masuk')
                ->where('id', '<', $lastReset->id)
                ->orderByDesc('id')
                ->first();

            if (! $lastNotaBeforeReset) {
                $cutoff = $lastReset->created_at;
            } elseif (is_null($lastNotaBeforeReset->referensi_type)) {
                // Log sebelum reset berasal dari OPNAME STOK KAYU
                $cutoff = $lastNotaBeforeReset->created_at;
            } else {
                $referensiNota = $lastNotaBeforeReset->referensi_id
                    ? NotaKayu::find($lastNotaBeforeReset->referensi_id)
                    : null;

                $cutoff = $referensiNota
                    ? $referensiNota->updated_at
                    : $lastReset->created_at;
            }
        }

        // 3) Ambil data DetailTurusanKayu aktif berdasarkan cutoff
        $data = DetailTurusanKayu::with([
            'lahan',
            'kayuMasuk.penggunaanSupplier',
            'kayuMasuk.notaKayu',
        ])
            ->where('lahan_id', $lahanId)
            ->whereHas('kayuMasuk.notaKayu')
            ->when($cutoff, function ($q) use ($cutoff) {
                $q->whereHas('kayuMasuk.notaKayu', function ($query) use ($cutoff) {
                    $query->where(function ($sub) use ($cutoff) {
                        $sub->where(function ($belumLunas) use ($cutoff) {
                            $belumLunas->where('status_pelunasan', 'not like', 'Lunas%')
                                ->where('created_at', '>', $cutoff);
                        })->orWhere(function ($sudahLunas) use ($cutoff) {
                            $sudahLunas->where('status_pelunasan', 'like', 'Lunas%')
                                ->where('updated_at', '>', $cutoff);
                        });
                    });
                });
            })
            ->get()
            ->groupBy('id_kayu_masuk')
            ->map(function ($rows) {
                $first = $rows->first();

                $statusPelunasan = $first->kayuMasuk?->notaKayu?->status_pelunasan;
                $isLunas = str_starts_with(strtolower(trim($statusPelunasan ?? '')), 'lunas');

                return [
                    'ID Kayu' => $first->id_kayu_masuk,
                    'ID Nota' => $first->kayuMasuk?->notaKayu?->id,
                    'No Nota' => $first->kayuMasuk?->notaKayu?->no_nota,
                    'Seri' => $first->kayuMasuk?->seri,
                    'Supplier' => trim($first->kayuMasuk?->penggunaanSupplier?->nama_supplier ?? ''),
                    'Status Pelunasan' => $statusPelunasan,
                    'Batang' => (int) $rows->sum('kuantitas'),
                    // round-then-sum per item (4 desimal), konsisten dengan NotaKayuController.
                    'Kubikasi' => (float) $rows->sum(fn ($r) => round($r->kubikasi, 4)),
                    'Panjang' => $rows->pluck('panjang')->unique()->sort()->implode(', '),
                    'Grade' => $rows->pluck('grade')->unique()->sort()->implode(', '),
                    'is_lunas' => $isLunas,
                    'is_opname' => false,
                ];
            })
            ->sortBy('ID Nota')
            ->values();

        // 4) Opname SETELAH reset (referensi_id null, id > lastReset->id)
        if ($lastReset) {
            $opnameSetelahReset = HppAverageLog::where('id_lahan', $lahanId)
                ->where('id', '>', $lastReset->id)
                ->whereNull('referensi_id')
                ->orderBy('id')
                ->get();

            foreach ($opnameSetelahReset as $log) {
                $deltaBatang = $log->tk_delta_batang !== null
                    ? (int) $log->tk_delta_batang
                    : (int) $log->stok_batang_after - (int) $log->stok_batang_before;

                $deltaKubikasi = $log->tk_delta_kubikasi !== null
                    ? round((float) $log->tk_delta_kubikasi, 4)
                    : round(
                        (float) ($log->stok_kubikasi_after ?? 0) - (float) ($log->stok_kubikasi_before ?? 0),
                        4
                    );

                if ($deltaBatang === 0 && $deltaKubikasi == 0.0) {
                    continue;
                }

                $data->push([
                    'ID Kayu' => null,
                    'ID Nota' => null,
                    'No Nota' => 'OPNAME #'.$log->id,
                    'Seri' => 'OPNAME',
                    'Supplier' => $log->keterangan ?? '-',
                    'Status Pelunasan' => null,
                    'Batang' => $deltaBatang,
                    'Kubikasi' => $deltaKubikasi,
                    'Panjang' => '',
                    'Grade' => '',
                    // Opname tidak boleh memblokir tombol "Lahan Penuh"
                    'is_lunas' => true,
                    'is_opname' => true,
                ]);
            }

            $data = $data->values();
        }

        self::$snapshot[$lahanId] = $data;

        return $data;
    }

    public static function forgetSnapshot(?int $lahanId = null): void
    {
        if ($lahanId === null) {
            self::$snapshot = [];
        } else {
            unset(self::$snapshot[$lahanId]);
        }
    }

    private static function semuaLunas(int $lahanId): bool
    {
        $data = self::getKayuAktif($lahanId);

        if ($data->isEmpty()) {
            return true;
        }

        return $data->every(fn ($row) => $row['is_lunas']);
    }

    private static function seriiBelumLunas(int $lahanId): Collection
    {
        return self::getKayuAktif($lahanId)
            ->filter(fn ($row) => ! $row['is_lunas'])
            ->pluck('Seri')
            ->filter()
            ->values();
    }

    public static function configure(Table $table): Table
    {
        // ✅ AUTO-SYNC: siapa pun yang membuka Tempat Kayu otomatis memicu
        // "Sinkronasi Semua Lahan Baru" (sekali per request, sebelum query tabel dibentuk).
        self::autoSinkronisasi();

        $user = Auth::user();

        $isGrader = $user->hasAnyRole(self::ROLE_GRADER);
        $isPengawas = $user->hasAnyRole(self::ROLE_PENGAWAS);
        $isAdmin = $user->hasAnyRole(self::ROLE_ADMIN);

        $bisaSerah = $isGrader || $isAdmin;
        $bisaTerima = $isPengawas || $isAdmin;

        return $table
            ->paginated(false)
            ->recordUrl(null)
            ->recordAction('cek_detail')
            ->modifyQueryUsing(function (Builder $query) use ($bisaSerah, $bisaTerima) {
                $query
                    ->join('hpp_average_summaries', 'tempat_kayus.id_lahan', '=', 'hpp_average_summaries.id_lahan')
                    ->select(
                        DB::raw('MIN(tempat_kayus.id) as id'),
                        'tempat_kayus.id_lahan',
                        'hpp_average_summaries.panjang as group_panjang',
                        'hpp_average_summaries.grade as group_grade',
                        'tempat_kayus.status',
                        'tempat_kayus.diserahkan_oleh',
                        'tempat_kayus.diterima_oleh',
                    )
                    ->groupBy(
                        'tempat_kayus.id_lahan',
                        'hpp_average_summaries.panjang',
                        'hpp_average_summaries.grade',
                        'tempat_kayus.status',
                        'tempat_kayus.diserahkan_oleh',
                        'tempat_kayus.diterima_oleh',
                    );

                // ── Sorting berdasarkan role ──────────────────────────────────────
                if ($bisaTerima) {
                    $query
                        ->orderByRaw("
                            CASE
                                WHEN tempat_kayus.status = 'sudah diserahkan'                           THEN 0
                                WHEN tempat_kayus.status = 'sudah diterima'                             THEN 1
                                WHEN (tempat_kayus.status IS NULL OR tempat_kayus.status = 'belum serah')
                                AND SUM(hpp_average_summaries.stok_batang) > 0                        THEN 2
                                WHEN (tempat_kayus.status IS NULL OR tempat_kayus.status = 'belum serah')
                                AND SUM(hpp_average_summaries.stok_batang) <= 0                       THEN 3
                                ELSE 3
                            END ASC
                        ")
                        ->orderBy('hpp_average_summaries.panjang', 'asc')
                        ->orderBy('tempat_kayus.id_lahan', 'asc');
                } elseif ($bisaSerah) {
                    $query
                        ->orderByRaw("
                            CASE
                                WHEN (tempat_kayus.status IS NULL OR tempat_kayus.status = 'belum serah')
                                AND SUM(hpp_average_summaries.stok_batang) > 0  THEN 0
                                WHEN tempat_kayus.status = 'sudah diserahkan'     THEN 1
                                WHEN tempat_kayus.status = 'sudah diterima'       THEN 2
                                WHEN (tempat_kayus.status IS NULL OR tempat_kayus.status = 'belum serah')
                                AND SUM(hpp_average_summaries.stok_batang) <= 0 THEN 3
                                ELSE 3
                            END ASC
                        ")
                        ->orderBy('hpp_average_summaries.panjang', 'asc')
                        ->orderByRaw('SUM(hpp_average_summaries.stok_batang) DESC')
                        ->orderBy('tempat_kayus.id_lahan', 'asc');
                }

                return $query;
            })
            ->columns([
                TextColumn::make('lahan.kode_lahan')
                    ->label('Lahan')
                    ->sortable(query: function (Builder $query, string $direction) {
                        $joins = collect($query->getQuery()->joins)->pluck('table');
                        if (! $joins->contains('lahans')) {
                            $query->join('lahans', 'tempat_kayus.id_lahan', '=', 'lahans.id');
                        }

                        return $query->reorder('lahans.kode_lahan', $direction);
                    })
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('group_panjang')
                    ->label('Pjg')
                    ->sortable(query: function (Builder $query, string $direction) {
                        return $query->reorder('hpp_average_summaries.panjang', $direction);
                    })
                    ->badge()
                    ->color(fn ($state) => $state == 260 ? 'success' : 'info')
                    ->toggleable(),

                TextColumn::make('jenis_kayu')
                    ->label('Jenis Kayu')
                    ->getStateUsing(function ($record) {
                        $aktif = self::getKayuAktif((int) $record->id_lahan);

                        // Cari data kayu aktif yang panjangnya sesuai group_panjang (fallback ke semua)
                        $matching = $aktif->where('is_opname', false)->filter(function ($item) use ($record) {
                            $panjangs = array_map('trim', explode(',', $item['Panjang'] ?? ''));

                            return in_array((string) $record->group_panjang, $panjangs);
                        });

                        if ($matching->isEmpty()) {
                            $matching = $aktif->where('is_opname', false);
                        }

                        $idKayuMasuks = $matching->pluck('ID Kayu')->filter()->unique();

                        if ($idKayuMasuks->isEmpty()) {
                            return '-';
                        }

                        $turusan = DetailTurusanKayu::with('jenisKayu')
                            ->where('lahan_id', $record->id_lahan)
                            ->whereIn('id_kayu_masuk', $idKayuMasuks)
                            ->first();

                        return $turusan?->jenisKayu?->nama_kayu ?: '-';
                    })
                    ->toggleable(),

                TextColumn::make('total_batang_riil')
                    ->label('Batang')
                    ->getStateUsing(function ($record) {
                        return (int) self::getKayuAktif((int) $record->id_lahan)->sum('Batang');
                    })
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('kubikasi_riil')
                    ->label('Volume (m³)')
                    ->getStateUsing(function ($record) {
                        // 'Kubikasi' sudah hasil round-then-sum per item (4 desimal),
                        // termasuk delta opname yang bisa minus.
                        $total = self::getKayuAktif((int) $record->id_lahan)->sum('Kubikasi');

                        return number_format((float) $total, 4, '.', ',');
                    })
                    ->color('primary')
                    ->toggleable(),

                TextColumn::make('diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->sortable(query: function (Builder $query, string $direction) {
                        return $query->reorder('tempat_kayus.diserahkan_oleh', $direction);
                    })
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('diterima_oleh')
                    ->sortable(query: function (Builder $query, string $direction) {
                        return $query->reorder('tempat_kayus.diterima_oleh', $direction);
                    })
                    ->label('Diterima Oleh')
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('status')
                    ->sortable(query: function (Builder $query, string $direction) {
                        return $query->reorder('tempat_kayus.status', $direction);
                    })
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'sudah diserahkan' => 'Diserahkan',
                        'sudah diterima' => 'Diterima',
                        default => 'Belum Diserahkan',
                    })
                    ->color(fn ($state) => match ($state) {
                        'sudah diterima' => 'success',
                        'sudah diserahkan' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('status_serah')
                    ->label('Status Serah')
                    ->placeholder('Semua Data')
                    ->trueLabel('Sudah Diserahkan')
                    ->falseLabel('Belum Diserahkan')
                    ->queries(
                        true: fn (Builder $query) => $query->where('tempat_kayus.status', 'sudah diserahkan'),
                        false: fn (Builder $query) => $query->where(function ($q) {
                            $q->whereNull('tempat_kayus.status')
                                ->orWhere('tempat_kayus.status', '!=', 'sudah diserahkan');
                        }),
                        blank: fn (Builder $query) => $query,
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->deferFilters(false)
            ->recordActions([

                // ── MODAL DETAIL ─────────────────────────────────────────────
                Action::make('cek_detail')
                    ->label('')
                    ->icon('')
                    ->color('gray')
                    ->extraAttributes(['style' => 'display: none;'])
                    ->modalHeading('Detail Seri & Stok Kayu')
                    ->modalSubmitAction(false)
                    ->modalWidth('4xl')
                    ->modalContent(function ($record) {
                        $kayuAktif = self::getKayuAktif((int) $record->id_lahan);

                        $totalStokRiil = (int) $kayuAktif->sum('Batang');

                        // 'Kubikasi' sudah round-then-sum per item (termasuk opname).
                        $totalKubikasiRiil = (float) $kayuAktif->sum('Kubikasi');

                        return view('filament.components.detail-kayu-modal', [
                            'record' => $record,
                            'details' => $kayuAktif,
                            'totalBatang' => $totalStokRiil,
                            'totalKubikasi' => $totalKubikasiRiil,
                        ]);
                    }),

                // ── ACTION SERAH ─────────────────────────────────────────────
                Action::make('serah_kayu')
                    ->label('Lahan Penuh')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Kayu?')
                    ->modalDescription(function ($record) {

                        $semuaSeri = HppAverageLog::where('id_lahan', $record->id_lahan)
                            ->where('panjang', $record->group_panjang)
                            ->where('referensi_type', NotaKayu::class)
                            ->with('referensi.kayuMasuk')
                            ->get()
                            ->map(fn ($log) => (string) ($log->referensi?->kayuMasuk?->seri ?? null))
                            ->filter()
                            ->unique()
                            ->values();

                        $belumLunas = NotaKayu::whereHas(
                            'kayuMasuk',
                            fn ($q) => $q->whereIn('seri', $semuaSeri)
                        )
                            ->with('kayuMasuk')
                            ->get()
                            ->groupBy(fn ($nota) => (string) $nota->kayuMasuk?->seri)
                            ->map(fn ($group) => $group->sortByDesc('id')->first())
                            ->filter(
                                fn ($nota) => ! str_starts_with(strtolower(trim($nota->status_pelunasan ?? '')), 'lunas')
                            )
                            ->map(fn ($nota) => (string) $nota->kayuMasuk?->seri)
                            ->filter()
                            ->values();

                        if ($belumLunas->isNotEmpty()) {
                            return '⚠️ Seri berikut belum lunas: '.$belumLunas->implode(', ').'. Tidak dapat diserahkan.';
                        }

                        return "Kayu dari lahan {$record->lahan?->kode_lahan} akan diserahkan ke rotary. Semua seri sudah lunas.";
                    })
                    ->modalSubmitActionLabel('Ya, Serahkan')
                    ->visible(function ($record) use ($bisaSerah, $isAdmin) {
                        if (! $bisaSerah) {
                            return false;
                        }

                        // Disembunyikan untuk SEMUA role jika batang = 0
                        $totalBatangRiil = (int) self::getKayuAktif((int) $record->id_lahan)->sum('Batang');
                        if ($totalBatangRiil <= 0) {
                            return false;
                        }

                        // Disembunyikan untuk SEMUA role jika ada seri belum lunas
                        if (! self::semuaLunas((int) $record->id_lahan)) {
                            return false;
                        }

                        if ($isAdmin) {
                            return true;
                        }

                        if ($record->status !== 'belum serah' && $record->status !== null) {
                            return false;
                        }

                        $totalBatang = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->where('panjang', $record->group_panjang)
                            ->where('grade', $record->group_grade)
                            ->sum('stok_batang');

                        if ($totalBatang <= 0) {
                            return false;
                        }

                        return true;
                    })
                    ->action(function ($record) {

                        // Server-side guard (double-check)
                        $belumLunas = self::seriiBelumLunas((int) $record->id_lahan);

                        if ($belumLunas->isNotEmpty()) {
                            Notification::make()
                                ->title('Tidak dapat diserahkan!')
                                ->body('Seri '.$belumLunas->implode(', ').' belum lunas. Selesaikan pembayaran terlebih dahulu.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        try {
                            DB::transaction(function () use ($record) {
                                $totalBatang = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                                    ->where('panjang', $record->group_panjang)
                                    ->whereNull('grade')
                                    ->sum('stok_batang');

                                // Bulatkan kubikasi setelah diambil dari DB
                                $kubikasi = round(
                                    (float) HppAverageSummarie::where('id_lahan', $record->id_lahan)
                                        ->where('panjang', $record->group_panjang)
                                        ->whereNull('grade')
                                        ->sum('stok_kubikasi'),
                                    4
                                );

                                $pivotAda = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                    ->where('id_lahan', $record->id_lahan)
                                    ->where('tipe', 'lahan_rotary')
                                    ->exists();

                                if ($pivotAda) {
                                    DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                        ->where('id_lahan', $record->id_lahan)
                                        ->where('tipe', 'lahan_rotary')
                                        ->update([
                                            'jumlah_batang' => max(0, $totalBatang),
                                            'kubikasi' => max(0, $kubikasi),
                                            'diserahkan_oleh' => Auth::user()->name,
                                            'diterima_oleh' => '-',
                                            'status' => 'Lahan Siap',
                                            'updated_at' => now(),
                                        ]);
                                } else {
                                    DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                        ->insert([
                                            'id_detail_hasil_palet_rotary' => null,
                                            'id_lahan' => $record->id_lahan,
                                            'id_produksi' => null,
                                            'jumlah_batang' => max(0, $totalBatang),
                                            'kubikasi' => max(0, $kubikasi),
                                            'diserahkan_oleh' => Auth::user()->name,
                                            'diterima_oleh' => '-',
                                            'tipe' => 'lahan_rotary',
                                            'status' => 'Lahan Siap',
                                            'created_at' => now(),
                                            'updated_at' => now(),
                                        ]);
                                }

                                DB::table('tempat_kayus')
                                    ->where('id_lahan', $record->id_lahan)
                                    ->update([
                                        'diserahkan_oleh' => Auth::user()->name,
                                        'diterima_oleh' => null,
                                        'status' => 'sudah diserahkan',
                                        'updated_at' => now(),
                                    ]);
                            });

                            Notification::make()
                                ->title('Kayu berhasil diserahkan')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Log::channel('single')->error('Serah Kayu FAILED', [
                                'message' => $e->getMessage(),
                                'code' => $e->getCode(),
                            ]);

                            Notification::make()
                                ->title('Gagal menyerahkan kayu')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // ── ACTION TERIMA ─────────────────────────────────────────────
                Action::make('terima_kayu')
                    ->label('Terima Kayu')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Terima Kayu dari Grader?')
                    ->modalDescription(
                        fn ($record) => "Kayu dari lahan {$record->lahan?->kode_lahan} akan diterima atas nama ".
                            Auth::user()->name.'.'
                    )
                    ->modalSubmitActionLabel('Ya, Terima')
                    ->visible(function ($record) use ($bisaTerima) {
                        if (! $bisaTerima) {
                            return false;
                        }

                        return $record->status === 'sudah diserahkan';
                    })
                    ->action(function ($record) {
                        try {
                            DB::transaction(function () use ($record) {
                                DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                    ->where('id_lahan', $record->id_lahan)
                                    ->where('tipe', 'lahan_rotary')
                                    ->update([
                                        'diterima_oleh' => Auth::user()->name,
                                        'status' => 'Sudah Diterima',
                                        'updated_at' => now(),
                                    ]);

                                DB::table('tempat_kayus')
                                    ->where('id_lahan', $record->id_lahan)
                                    ->update([
                                        'diterima_oleh' => Auth::user()->name,
                                        'status' => 'sudah diterima',
                                        'updated_at' => now(),
                                    ]);
                            });

                            Notification::make()
                                ->title('Kayu berhasil diterima')
                                ->body('Status lahan diperbarui menjadi Sudah Diterima.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Log::channel('single')->error('Terima Kayu FAILED', [
                                'message' => $e->getMessage(),
                                'code' => $e->getCode(),
                                'trace' => $e->getTraceAsString(),
                            ]);

                            Notification::make()
                                ->title('Gagal menerima kayu')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // ── ACTION BATAL SERAH ───────────────────────────────────────
                Action::make('batal_serah_kayu')
                    ->label('Batal Serah')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan Serah Kayu?')
                    ->modalDescription(
                        fn ($record) => "Status lahan {$record->lahan?->kode_lahan} akan dikembalikan menjadi Belum Diserahkan."
                    )
                    ->modalSubmitActionLabel('Ya, Batalkan')
                    ->visible(function ($record) use ($isAdmin) {
                        // Hanya Admin/Super Admin
                        if (! $isAdmin) {
                            return false;
                        }

                        // Muncul hanya saat status 'sudah diserahkan'
                        return $record->status === 'sudah diserahkan';
                    })
                    ->action(function ($record) {
                        try {
                            DB::transaction(function () use ($record) {
                                DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                    ->where('id_lahan', $record->id_lahan)
                                    ->where('tipe', 'lahan_rotary')
                                    ->delete();

                                DB::table('tempat_kayus')
                                    ->where('id_lahan', $record->id_lahan)
                                    ->update([
                                        'diserahkan_oleh' => null,
                                        'diterima_oleh' => null,
                                        'status' => 'belum serah',
                                        'updated_at' => now(),
                                    ]);
                            });

                            Notification::make()
                                ->title('Serah kayu berhasil dibatalkan')
                                ->body('Status lahan dikembalikan menjadi Belum Diserahkan.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Log::channel('single')->error('Batal Serah Kayu FAILED', [
                                'message' => $e->getMessage(),
                                'code' => $e->getCode(),
                                'trace' => $e->getTraceAsString(),
                            ]);

                            Notification::make()
                                ->title('Gagal membatalkan serah kayu')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible($isAdmin),
                ]),
            ])
            ->headerActions([
                // Tombol manual tetap dipertahankan (memakai method yang sama dengan auto-sync).
                Action::make('sync_lahan_baru')
                    ->label('Sinkronasi Semua Lahan Baru')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Sinkronasi Semua Lahan Baru')
                    ->modalDescription('Apakah Anda yakin ingin memproses dan mensinkronisasikan semua data lahan baru (yang belum disinkronisasi) ke HPP Average Log dan Tempat Kayu?')
                    ->modalSubmitActionLabel('Ya, Sinkronisasi')
                    ->action(function () {
                        $hasil = self::sinkronisasiLahanBaru();

                        if ($hasil['summary'] === 0 && $hasil['tempat_kayu'] === 0) {
                            Notification::make()
                                ->title('Semua lahan sudah tersinkronisasi.')
                                ->info()
                                ->send();

                            return;
                        }

                        self::forgetSnapshot();

                        Notification::make()
                            ->title('Sinkronasi Berhasil')
                            ->body("Summary dibuat: {$hasil['summary']} | Tempat Kayu dibuat: {$hasil['tempat_kayu']} lahan")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
