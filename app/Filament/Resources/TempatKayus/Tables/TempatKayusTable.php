<?php

namespace App\Filament\Resources\TempatKayus\Tables;

use App\Models\DetailTurusanKayu;
use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
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

    private static function getKayuAktif(int $lahanId): Collection
    {
        if (isset(self::$snapshot[$lahanId])) {
            return self::$snapshot[$lahanId];
        }

        $lastReset = HppAverageLog::where('id_lahan', $lahanId)
            ->where('tipe_transaksi', 'keluar')
            ->where('stok_batang_after', 0)
            ->orderByDesc('id')
            ->first();

        if (! $lastReset) {
            $startNotaId = null;
        } else {
            $lastNotaBeforeReset = HppAverageLog::where('id_lahan', $lahanId)
                ->where('tipe_transaksi', 'masuk')
                ->where('id', '<', $lastReset->id)
                ->orderByDesc('id')
                ->first();

            $startNotaId = $lastNotaBeforeReset?->referensi_id;
        }

        $data = DetailTurusanKayu::with([
            'lahan',
            'kayuMasuk.penggunaanSupplier',
            'kayuMasuk.notaKayu',
        ])
            ->where('lahan_id', $lahanId)
            ->when($startNotaId, function ($q) use ($startNotaId) {
                $q->whereHas('kayuMasuk.notaKayu', fn ($q) => $q->where('id', '>=', $startNotaId));
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
                    // ✅ DIUBAH: ikut pola NotaKayuController → round PER-ITEM (4 desimal) dulu,
                    // baru di-sum. Ini memastikan total kubikasi selalu match dengan
                    // angka per-baris yang dilihat user (round-then-sum), bukan sebaliknya.
                    'Kubikasi' => (float) $rows->sum(fn ($r) => round($r->kubikasi, 4)),
                    'Panjang' => $rows->pluck('panjang')->unique()->sort()->implode(', '),
                    'Grade' => $rows->pluck('grade')->unique()->sort()->implode(', '),
                    'is_lunas' => $isLunas,
                ];
            })
            ->sortBy('ID Nota')
            ->values();

        if ($startNotaId) {
            $data = $data
                ->reject(fn ($row) => $row['ID Nota'] == $startNotaId)
                ->values();
        }

        self::$snapshot[$lahanId] = $data;

        return $data;
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
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('group_panjang')
                    ->label('Pjg')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state == 260 ? 'success' : 'info')
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
                        // ✅ DIUBAH: 'Kubikasi' di getKayuAktif() sudah hasil round-then-sum
                        // per item (4 desimal). Tidak perlu round lagi di sini agar tidak dobel
                        // pembulatan dan tetap konsisten dengan pola NotaKayuController.
                        $total = self::getKayuAktif((int) $record->id_lahan)->sum('Kubikasi');

                        return number_format((float) $total, 4, '.', ',');
                    })
                    ->color('primary')
                    ->toggleable(),

                TextColumn::make('diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->sortable()
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('diterima_oleh')
                    ->sortable()
                    ->label('Diterima Oleh')
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('status')
                    ->sortable()
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

                        // ✅ DIUBAH: 'Kubikasi' sudah round-then-sum per item di getKayuAktif().
                        // Tidak perlu round lagi di sini (hindari dobel pembulatan).
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
                        $belumLunas = self::seriiBelumLunas((int) $record->id_lahan);

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

                        // Tombol disembunyikan untuk SEMUA role jika batang = 0
                        $totalBatangRiil = (int) self::getKayuAktif((int) $record->id_lahan)->sum('Batang');
                        if ($totalBatangRiil <= 0) {
                            return false;
                        }

                        // Tombol disembunyikan untuk SEMUA role jika ada seri belum lunas
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

                        // Server-side guard (tetap ada sebagai double-check)
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

            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible($isAdmin),
                ]),
            ]);
    }
}
