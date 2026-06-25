<?php

namespace App\Filament\Resources\TempatKayus\Tables;

use App\Models\HppAverageSummarie;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TempatKayusTable
{
    private const ROLE_GRADER = ['Grader Kayu 1', 'Grader Kayu 2'];
    private const ROLE_PENGAWAS = ['pengawas_rotary_1', 'pengawas_rotary_2'];
    private const ROLE_ADMIN = ['super_admin', 'Super Admin', 'admin_kayu'];

    // Tambahkan method ini di dalam class TempatKayusTable
    private static function getCekBelumLunas($record): \Illuminate\Support\Collection
    {
        $lastResetLogId = \App\Models\HppAverageLog::where('id_lahan', $record->id_lahan)
            ->where('panjang', $record->group_panjang)
            ->where('stok_batang_after', 0)
            ->latest('id')
            ->value('id');

        $notaIdDariLog = \App\Models\HppAverageLog::where('id_lahan', $record->id_lahan)
            ->where('panjang', $record->group_panjang)
            ->where('tipe_transaksi', 'masuk')
            ->where('referensi_type', \App\Models\NotaKayu::class)
            ->when($lastResetLogId, fn($q) => $q->where('id', '>', $lastResetLogId))
            ->pluck('referensi_id')
            ->unique()
            ->values();

        // ── Pengecekan 1: dari log ────────────────────────────────
        $belumLunasDariLog = \App\Models\NotaKayu::whereIn('id', $notaIdDariLog)
            ->with('kayuMasuk:id,seri')
            ->get()
            ->groupBy(fn($nota) => (string) $nota->kayuMasuk?->seri)
            ->map(fn($group) => $group->sortByDesc('id')->first())
            ->filter(
                fn($nota) =>
                !str_starts_with(strtolower(trim($nota->status_pelunasan ?? '')), 'lunas')
            )
            ->map(fn($nota) => (string) $nota->kayuMasuk?->seri) // ✅ Pastikan string
            ->filter(fn($seri) => !empty($seri))
            ->values();

        // ── Pengecekan 2: nota belum lunas periode aktif ──────────
        $notaIdTerkecilDiLog = $notaIdDariLog->min();

        $belumLunasBaru = collect(); // ✅ Default plain collect()

        if ($notaIdTerkecilDiLog) {
            $belumLunasBaru = \App\Models\NotaKayu::where('status_pelunasan', 'Belum Lunas')
                ->where('id', '>=', $notaIdTerkecilDiLog)
                ->whereNotIn('id', $notaIdDariLog)
                ->whereHas(
                    'kayuMasuk.detailTurusanKayus',
                    fn($q) =>
                    $q->where('lahan_id', $record->id_lahan)
                        ->where('panjang', $record->group_panjang)
                )
                ->with('kayuMasuk:id,seri')
                ->get()
                ->map(fn($nota) => (string) $nota->kayuMasuk?->seri) // ✅ Pastikan string
                ->filter(fn($seri) => !empty($seri))
                ->values();
        }

        // ✅ Gunakan collect() biasa, bukan Eloquent Collection
        // untuk hindari error getKey() on string
        return collect()
            ->concat($belumLunasDariLog) // ← concat aman untuk string
            ->concat($belumLunasBaru)    // ← concat aman untuk string
            ->unique()
            ->values();
    }

    public const MESIN_MAP = [
        130 => ['SANJI', 'YUEQUN'],
        260 => ['SPINDLESS', 'MERANTI'],
    ];

    public static function configure(Table $table): Table
    {
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
            ->modifyQueryUsing(function (Builder $query) use ($bisaSerah, $bisaTerima, $isAdmin) {
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
                        // ✅ Subquery: hitung nota belum lunas di periode aktif
                        DB::raw('(
                SELECT COUNT(*)
                FROM nota_kayus nk2
                JOIN kayu_masuks km2 ON km2.id = nk2.id_kayu_masuk
                JOIN detail_turusan_kayus dtk2 ON dtk2.id_kayu_masuk = km2.id
                WHERE dtk2.lahan_id = tempat_kayus.id_lahan
                AND dtk2.panjang = hpp_average_summaries.panjang
                AND nk2.status_pelunasan = "Belum Lunas"
                AND nk2.id >= (
                    SELECT MIN(referensi_id)
                    FROM hpp_average_logs
                    WHERE id_lahan = tempat_kayus.id_lahan
                    AND panjang = hpp_average_summaries.panjang
                    AND tipe_transaksi = "masuk"
                    AND referensi_type = "App\\\\Models\\\\NotaKayu"
                )
            ) as count_belum_lunas'),
                    )
                    ->groupBy(
                        'tempat_kayus.id_lahan',
                        'hpp_average_summaries.panjang',
                        'hpp_average_summaries.grade',
                        'tempat_kayus.status',
                        'tempat_kayus.diserahkan_oleh',
                        'tempat_kayus.diterima_oleh',
                    );

                if ($bisaTerima) {
                    $query->orderByRaw("
            CASE
                WHEN tempat_kayus.status = 'sudah diserahkan'
                    THEN 0
                WHEN tempat_kayus.status = 'sudah diterima'
                    THEN 1
                WHEN (tempat_kayus.status IS NULL OR tempat_kayus.status = 'belum serah')
                    AND SUM(hpp_average_summaries.stok_batang) > 0
                    AND count_belum_lunas = 0
                    THEN 2
                WHEN (tempat_kayus.status IS NULL OR tempat_kayus.status = 'belum serah')
                    AND count_belum_lunas > 0
                    THEN 3
                WHEN (tempat_kayus.status IS NULL OR tempat_kayus.status = 'belum serah')
                    AND SUM(hpp_average_summaries.stok_batang) <= 0
                    THEN 4
                ELSE 4
            END ASC
        ")
                        ->orderBy('hpp_average_summaries.panjang', 'asc')
                        ->orderBy('tempat_kayus.id_lahan', 'asc');
                } elseif ($bisaSerah) {
                    $query->orderByRaw("
            CASE
                -- ✅ Priority 0: Penuh + semua lunas → siap serah
                WHEN (tempat_kayus.status IS NULL OR tempat_kayus.status = 'belum serah')
                    AND SUM(hpp_average_summaries.stok_batang) > 0
                    AND count_belum_lunas = 0
                    THEN 0
                -- ✅ Priority 1: Ada isi tapi belum lunas → perlu bayar dulu
                WHEN (tempat_kayus.status IS NULL OR tempat_kayus.status = 'belum serah')
                    AND count_belum_lunas > 0
                    THEN 1
                -- ✅ Priority 2: Sudah diserahkan
                WHEN tempat_kayus.status = 'sudah diserahkan'
                    THEN 2
                -- ✅ Priority 3: Sudah diterima
                WHEN tempat_kayus.status = 'sudah diterima'
                    THEN 3
                -- ✅ Priority 4: Kosong
                WHEN (tempat_kayus.status IS NULL OR tempat_kayus.status = 'belum serah')
                    AND SUM(hpp_average_summaries.stok_batang) <= 0
                    THEN 4
                ELSE 4
            END ASC
        ")
                        ->orderBy('hpp_average_summaries.panjang', 'asc')
                        ->orderByRaw("SUM(hpp_average_summaries.stok_batang) DESC")
                        ->orderBy('tempat_kayus.id_lahan', 'asc');
                }

                return $query;
            })
            ->columns([
                TextColumn::make('lahan.kode_lahan')
                    ->label('Lahan')
                    ->sortable()
                    ->searchable(),

                // ✅ Seri diimplode menjadi satu baris
                // TextColumn::make('seri_kayu_gabungan')
                //     ->label('Daftar Seri')
                //     ->getStateUsing(function ($record) {
                //         return DB::table('tempat_kayus')
                //             ->join('kayu_masuks', 'tempat_kayus.id_kayu_masuk', '=', 'kayu_masuks.id')
                //             ->where('tempat_kayus.id_lahan', $record->id_lahan)
                //             ->distinct()
                //             ->orderBy('kayu_masuks.seri')
                //             ->pluck('kayu_masuks.seri')
                //             ->filter()
                //             ->implode(', ');
                //     })
                //     ->wrap()
                //     ->color('primary')
                //     ->weight('bold'),

                TextColumn::make('group_panjang')
                    ->label('Pjg')
                    ->sortable()
                    ->badge()
                    ->color(fn($state) => $state == 260 ? 'success' : 'info'),

                // ✅ Jumlah batang dari HppAverageSummarie sesuai stok aktual
                TextColumn::make('total_batang_riil')
                    ->label('Batang')
                    ->getStateUsing(function ($record) {
                        return (int) max(
                            0,
                            HppAverageSummarie::where('id_lahan', $record->id_lahan)
                                ->where('panjang', $record->group_panjang)
                                ->where('grade', $record->group_grade)
                                ->sum('stok_batang')
                        );
                    })
                    ->numeric()
                    ->alignCenter(),

                // ✅ Kubikasi dari HppAverageSummarie sesuai stok aktual
                TextColumn::make('kubikasi_riil')
                    ->label('Volume (m³)')
                    ->getStateUsing(function ($record) {
                        $val = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->where('panjang', $record->group_panjang)
                            ->whereNull('grade')
                            ->sum('stok_kubikasi');

                        return number_format(max(0, $val), 4);
                    })
                    ->color(
                        fn($record) =>
                        HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->where('panjang', $record->group_panjang)
                            ->sum('stok_kubikasi') < 0 ? 'danger' : 'primary'
                    ),

                TextColumn::make('diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->sortable()
                    ->default('-'),

                TextColumn::make('diterima_oleh')
                    ->sortable()
                    ->label('Diterima Oleh')
                    ->default('-'),

                // ✅ Status langsung dari kolom tempat_kayus
                TextColumn::make('status')
                    ->sortable()
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'sudah diserahkan' => 'Diserahkan',
                        'sudah diterima' => 'Diterima',
                        default => 'Belum Diserahkan',
                    })
                    ->color(fn($state) => match ($state) {
                        'sudah diterima' => 'success',
                        'sudah diserahkan' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([])
            ->recordActions([
                // ... di dalam recordActions
                Action::make('cek_detail')
                    ->label('')
                    ->icon('')
                    ->color('gray')
                    ->extraAttributes(['style' => 'display: none;'])
                    ->modalHeading('Detail Seri & Stok Kayu')
                    ->modalSubmitAction(false)
                    ->modalWidth('4xl')
                    ->modalContent(function ($record) {

                        // ── 1. Cari titik reset terakhir ──────────────────────────
                        $lastResetLogId = \App\Models\HppAverageLog::where('id_lahan', $record->id_lahan)
                            ->where('panjang', $record->group_panjang)
                            ->where('stok_batang_after', 0)
                            ->latest('id')
                            ->value('id');

                        // ── 2. Ambil logs ─────────────────────────────────────────
                        $query = \App\Models\HppAverageLog::where('id_lahan', $record->id_lahan)
                            ->where('panjang', $record->group_panjang)
                            ->with(['referensi']);

                        if ($lastResetLogId) {
                            $query->where('id', '>', $lastResetLogId);
                        }

                        $logs = $query->orderBy('tanggal', 'asc')
                            ->orderBy('id', 'asc')
                            ->get();

                        // ── 3. Simulasi FIFO queue ─────────────────────────────────
                        $queue = [];

                        foreach ($logs as $log) {
                            $isM  = $log->tipe_transaksi === 'masuk';
                            $seri = 'Tanpa Seri';

                            if (
                                $log->referensi_type === \App\Models\NotaKayu::class ||
                                $log->referensi_type === 'NotaKayu'
                            ) {
                                if ($log->referensi) {
                                    $log->referensi->loadMissing('kayuMasuk');
                                    $seri = $log->referensi->kayuMasuk?->seri ?? 'Tanpa Seri';
                                }
                            }

                            if ($seri === 'Tanpa Seri') {
                                if (preg_match('/SERI:\s*(\d+)/i', $log->keterangan, $matches)) {
                                    $seri = $matches[1];
                                }
                            }

                            if ($isM) {
                                $queue[] = [
                                    'seri'          => $seri,
                                    'qty_left'      => $log->total_batang,
                                    'kubikasi_left' => (float) $log->total_kubikasi,
                                ];
                            } else {
                                $qtyKeluar      = $log->total_batang;
                                $kubikasiKeluar = (float) $log->total_kubikasi;

                                while ($qtyKeluar > 0 && !empty($queue)) {
                                    $firstKey = array_key_first($queue);
                                    $item     = &$queue[$firstKey];

                                    if ($item['qty_left'] <= $qtyKeluar) {
                                        $qtyKeluar      -= $item['qty_left'];
                                        $kubikasiKeluar -= $item['kubikasi_left'];
                                        unset($queue[$firstKey]);
                                    } else {
                                        $fraction              = $qtyKeluar / $item['qty_left'];
                                        $consumedKubikasi      = $item['kubikasi_left'] * $fraction;
                                        $item['qty_left']      = max(0, $item['qty_left'] - $qtyKeluar);
                                        $item['kubikasi_left'] = max(0.0, $item['kubikasi_left'] - $consumedKubikasi);
                                        $qtyKeluar             = 0;
                                    }
                                }

                                $queue = array_values($queue);
                            }
                        }

                        // ── 4. Pelunasan dari log (sudah lunas) ───────────────────
                        $notaIdDariLog = \App\Models\HppAverageLog::where('id_lahan', $record->id_lahan)
                            ->where('panjang', $record->group_panjang)
                            ->where('tipe_transaksi', 'masuk')
                            ->where('referensi_type', \App\Models\NotaKayu::class)
                            ->when($lastResetLogId, fn($q) => $q->where('id', '>', $lastResetLogId))
                            ->pluck('referensi_id')
                            ->unique()
                            ->values();

                        $pelunasanDariLog = \App\Models\NotaKayu::whereIn('id', $notaIdDariLog)
                            ->with('kayuMasuk:id,seri')
                            ->get()
                            ->groupBy(fn($nota) => (string) $nota->kayuMasuk?->seri)
                            ->map(fn($group) => $group->sortByDesc('id')->first());

                        // ── 5. ✅ Nota BELUM LUNAS yang relevan di periode aktif ──
                        // Kunci: gunakan id nota terkecil yang sudah masuk log
                        // sebagai batas bawah periode aktif
                        // Nota lama (id < batas) = tidak relevan, skip
                        $notaIdTerkecilDiLog = $notaIdDariLog->min(); // ← id terkecil dari log periode aktif

                        $notaBelumLunas = collect();

                        if ($notaIdTerkecilDiLog) {
                            $notaBelumLunas = \App\Models\NotaKayu::where('status_pelunasan', 'Belum Lunas')
                                // ✅ Hanya nota yang id >= nota terkecil di log periode aktif
                                // Nota lama Feb (id 814, 815) → id < batas → terfilter otomatis
                                ->where('id', '>=', $notaIdTerkecilDiLog)
                                // ✅ Yang sudah di log = sudah lunas, skip
                                ->whereNotIn('id', $notaIdDariLog)
                                // ✅ Hanya yang terhubung ke lahan + panjang ini via DetailTurusanKayu
                                ->whereHas(
                                    'kayuMasuk.detailTurusanKayus',
                                    fn($q) =>
                                    $q->where('lahan_id', $record->id_lahan)
                                        ->where('panjang', $record->group_panjang)
                                )
                                ->with('kayuMasuk:id,seri')
                                ->get()
                                ->groupBy(fn($nota) => (string) $nota->kayuMasuk?->seri)
                                ->map(fn($group) => $group->sortByDesc('id')->first());
                        }

                        // ── 6. Gabungkan: log (prioritas) + belum lunas ───────────
                        $pelunasanBySeri = $pelunasanDariLog->union($notaBelumLunas);

                        // ── 7. lastIdBySeri untuk sorting ─────────────────────────
                        $lastIdBySeri = \App\Models\HppAverageLog::where('id_lahan', $record->id_lahan)
                            ->where('panjang', $record->group_panjang)
                            ->where('referensi_type', \App\Models\NotaKayu::class)
                            ->when($lastResetLogId, fn($q) => $q->where('id', '>', $lastResetLogId))
                            ->with('referensi.kayuMasuk')
                            ->get()
                            ->map(fn($log) => [
                                'seri' => (string) ($log->referensi?->kayuMasuk?->seri ?? null),
                                'id'   => $log->id,
                            ])
                            ->filter(fn($item) => !empty($item['seri']) && $item['seri'] !== 'Tanpa Seri')
                            ->groupBy('seri')
                            ->map(fn($group) => $group->sortByDesc('id')->first()['id']);

                        // ── 8. Bangun groupedBySeri dari queue (stok aktif) ───────
                        $groupedBySeri = collect($queue)
                            ->groupBy('seri')
                            ->map(function ($group, $seri) use ($pelunasanBySeri, $lastIdBySeri) {
                                return [
                                    'seri'             => $seri,
                                    'total_batang'     => $group->sum('qty_left'),
                                    'total_kubikasi'   => $group->sum('kubikasi_left'),
                                    'status_pelunasan' => $pelunasanBySeri[(string) $seri]?->status_pelunasan ?? null,
                                    'last_seen'        => $lastIdBySeri[(string) $seri] ?? PHP_INT_MAX,
                                ];
                            });

                        // ── 9. ✅ Sisipkan nota belum lunas yang belum ada di stok ─

                        foreach ($notaBelumLunas as $seri => $nota) {
                            if ($groupedBySeri->has($seri)) {
                                continue;
                            }

                            // ✅ Ambil total batang & kubikasi dari DetailTurusanKayu
                            // karena nota belum lunas belum masuk HppAverageLog
                            $detailTurusan = \App\Models\DetailTurusanKayu::where('id_kayu_masuk', $nota->kayuMasuk?->id)
                                ->where('lahan_id', $record->id_lahan)
                                ->where('panjang', $record->group_panjang)
                                ->get();

                            $totalBatangBelumLunas   = $detailTurusan->sum('kuantitas');
                            $totalKubikasibelumLunas = $detailTurusan->sum(
                                fn($d) => ($d->panjang * $d->diameter * $d->diameter * $d->kuantitas * 0.785) / 1_000_000
                            );

                            $groupedBySeri->put($seri, [
                                'seri'             => $seri,
                                'total_batang'     => $totalBatangBelumLunas,       // ✅ Dari DetailTurusanKayu
                                'total_kubikasi'   => $totalKubikasibelumLunas,     // ✅ Dari DetailTurusanKayu
                                'status_pelunasan' => 'Belum Lunas',
                                'last_seen'        => 0,
                            ]);
                        }

                        // ── 10. Sort: yang ada di log dulu, belum lunas di bawah ──
                        $groupedBySeri = $groupedBySeri
                            ->sortByDesc('last_seen')
                            ->values();

                        // ── 11. Total dari HppAverageSummarie ─────────────────────
                        $summary = \App\Models\HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->where('panjang', $record->group_panjang)
                            ->where('grade', $record->group_grade)
                            ->selectRaw('SUM(stok_batang) as total_batang, SUM(stok_kubikasi) as total_kubikasi')
                            ->first();

                        $totalStokRiil     = (int) max(0, $summary->total_batang ?? 0);
                        $totalKubikasiRiil = (float) max(0, $summary->total_kubikasi ?? 0);

                        return view('filament.components.detail-kayu-modal', [
                            'record'        => $record,
                            'details'       => $groupedBySeri,
                            'totalBatang'   => $totalStokRiil,
                            'totalKubikasi' => $totalKubikasiRiil,
                        ]);
                    }),

                // ACTION SERAH
                Action::make('serah_kayu')
                    ->label('Penuh')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Kayu?')
                    ->modalDescription(function ($record) {
                        $belumLunas = self::getCekBelumLunas($record); // ✅ Pakai helper

                        if ($belumLunas->isNotEmpty()) {
                            return "⚠️ Seri berikut belum lunas: " . $belumLunas->implode(', ') . ". Tidak dapat diserahkan.";
                        }

                        return "Kayu dari lahan {$record->lahan?->kode_lahan} akan diserahkan ke rotary. Semua seri sudah lunas.";
                    })
                    ->modalSubmitActionLabel('Ya, Serahkan')
                    ->visible(function ($record) use ($bisaSerah, $isAdmin) {
                        if (!$bisaSerah) return false;
                        if ($isAdmin) return true;
                        if ($record->status !== 'belum serah' && $record->status !== null) {
                            return false;
                        }

                        $totalBatang = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->where('panjang', $record->group_panjang)
                            ->where('grade', $record->group_grade)
                            ->sum('stok_batang');

                        if ($totalBatang <= 0) return false;

                        // ✅ Pakai helper — tombol tidak muncul kalau ada yang belum lunas
                        $belumLunas = self::getCekBelumLunas($record);
                        return $belumLunas->isEmpty();
                    })
                    ->action(function ($record) {
                        // ✅ Server-side guard — cek ulang dengan helper yang sama
                        $belumLunas = self::getCekBelumLunas($record);

                        if ($belumLunas->isNotEmpty()) {
                            Notification::make()
                                ->title('Tidak dapat diserahkan!')
                                ->body('Seri ' . $belumLunas->implode(', ') . ' belum lunas. Selesaikan pembayaran terlebih dahulu.')
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        // ── Proses serah ──────────────────────────────────────
                        try {
                            DB::transaction(function () use ($record) {
                                $totalBatang = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                                    ->where('panjang', $record->group_panjang)
                                    ->whereNull('grade')
                                    ->sum('stok_batang');

                                $kubikasi = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                                    ->where('panjang', $record->group_panjang)
                                    ->whereNull('grade')
                                    ->sum('stok_kubikasi');

                                $pivotAda = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                    ->where('id_lahan', $record->id_lahan)
                                    ->where('tipe', 'lahan_rotary')
                                    ->exists();

                                if ($pivotAda) {
                                    DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                        ->where('id_lahan', $record->id_lahan)
                                        ->where('tipe', 'lahan_rotary')
                                        ->update([
                                            'jumlah_batang'   => max(0, $totalBatang),
                                            'kubikasi'        => max(0, $kubikasi),
                                            'diserahkan_oleh' => Auth::user()->name,
                                            'diterima_oleh'   => '-',
                                            'status'          => 'Lahan Siap',
                                            'updated_at'      => now(),
                                        ]);
                                } else {
                                    DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                        ->insert([
                                            'id_detail_hasil_palet_rotary' => null,
                                            'id_lahan'        => $record->id_lahan,
                                            'id_produksi'     => null,
                                            'jumlah_batang'   => max(0, $totalBatang),
                                            'kubikasi'        => max(0, $kubikasi),
                                            'diserahkan_oleh' => Auth::user()->name,
                                            'diterima_oleh'   => '-',
                                            'tipe'            => 'lahan_rotary',
                                            'status'          => 'Lahan Siap',
                                            'created_at'      => now(),
                                            'updated_at'      => now(),
                                        ]);
                                }

                                DB::table('tempat_kayus')
                                    ->where('id_lahan', $record->id_lahan)
                                    ->update([
                                        'diserahkan_oleh' => Auth::user()->name,
                                        'diterima_oleh'   => null,
                                        'status'          => 'sudah diserahkan',
                                        'updated_at'      => now(),
                                    ]);
                            });

                            Notification::make()
                                ->title('Kayu berhasil diserahkan')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Log::channel('single')->error('Serah Kayu FAILED', [
                                'message' => $e->getMessage(),
                                'code'    => $e->getCode(),
                            ]);

                            Notification::make()
                                ->title('Gagal menyerahkan kayu')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // ACTION TERIMA
                Action::make('terima_kayu')
                    ->label('Terima')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Terima Kayu dari Grader?')
                    ->modalDescription(
                        fn($record) =>
                        "Kayu dari lahan {$record->lahan?->kode_lahan} akan diterima atas nama " .
                            Auth::user()->name . "."
                    )
                    ->modalSubmitActionLabel('Ya, Terima')
                    ->visible(function ($record) use ($bisaTerima) {
                        if (!$bisaTerima)
                            return false;

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

                                // ✅ Update semua row tempat_kayus dengan id_lahan yang sama
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
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible($isAdmin),
                ]),
            ]);
    }
}
