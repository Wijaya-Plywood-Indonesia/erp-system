<?php

namespace App\Filament\Resources\TempatKayus\Tables;

use App\Models\HppAverageSummarie;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TempatKayusTable
{
    private const ROLE_GRADER   = ['Grader Kayu 1', 'Grader Kayu 2'];
    private const ROLE_PENGAWAS = ['pengawas_rotary_1', 'pengawas_rotary_2'];
    private const ROLE_ADMIN    = ['super_admin', 'Super Admin', 'admin_kayu'];

    public const MESIN_MAP = [
        130 => ['SANJI', 'YUEQUN'],
        260 => ['SPINDLESS', 'MERANTI'],
    ];

    public static function configure(Table $table): Table
    {
        $user = Auth::user();

        $isGrader   = $user->hasAnyRole(self::ROLE_GRADER);
        $isPengawas = $user->hasAnyRole(self::ROLE_PENGAWAS);
        $isAdmin    = $user->hasAnyRole(self::ROLE_ADMIN);

        $bisaSerah  = $isGrader || $isAdmin;
        $bisaTerima = $isPengawas || $isAdmin;

        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    // ✅ Sertakan id agar Filament bisa identifikasi record
                    ->select(
                        DB::raw('MIN(tempat_kayus.id) as id'),
                        'tempat_kayus.id_lahan',
                        DB::raw('SUM(tempat_kayus.jumlah_batang) as jumlah_batang'), // ✅ total semua nota
                        'tempat_kayus.status',
                        'tempat_kayus.diserahkan_oleh',
                        'tempat_kayus.diterima_oleh',
                        DB::raw('(SELECT panjang FROM hpp_average_summaries 
              WHERE id_lahan = tempat_kayus.id_lahan 
              AND grade IS NULL 
              LIMIT 1) as group_panjang')
                    )
                    ->groupBy(
                        'tempat_kayus.id_lahan',
                        'tempat_kayus.status',
                        'tempat_kayus.diserahkan_oleh',
                        'tempat_kayus.diterima_oleh',
                    );
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

                // ✅ Jumlah batang dari HppAverageSummarie sesuai stok aktual
                TextColumn::make('total_batang_riil')
                    ->label('Batang')
                    ->getStateUsing(function ($record) {
                        return (int) max(
                            0,
                            HppAverageSummarie::where('id_lahan', $record->id_lahan)
                                ->where('panjang', $record->group_panjang)
                                ->whereNull('grade')
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

                TextColumn::make('group_panjang')
                    ->label('Pjg')
                    ->badge()
                    ->color(fn($state) => $state == 260 ? 'success' : 'info'),

                TextColumn::make('diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->default('-'),

                TextColumn::make('diterima_oleh')
                    ->label('Diterima Oleh')
                    ->default('-'),

                // ✅ Status langsung dari kolom tempat_kayus
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'sudah diserahkan' => 'Diserahkan',
                        'sudah diterima'   => 'Diterima',
                        default            => 'Belum Diserahkan',
                    })
                    ->color(fn($state) => match ($state) {
                        'sudah diterima'   => 'success',
                        'sudah diserahkan' => 'warning',
                        default            => 'gray',
                    }),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->visible($isGrader || $isAdmin),
                // ACTION SERAH
                Action::make('serah_kayu')
                    ->label('Serah Kayu')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Kayu?')
                    ->modalDescription(
                        fn($record) =>
                        "Kayu dari lahan {$record->lahan?->kode_lahan} akan diserahkan ke rotary."
                    )
                    ->modalSubmitActionLabel('Ya, Serahkan')
                    ->visible(function ($record) use ($bisaSerah, $isAdmin) {
                        if (!$bisaSerah) return false;
                        if ($isAdmin) return true;

                        // Grader: hanya muncul jika belum diserahkan
                        return $record->status === 'belum serah' || $record->status === null;
                    })
                    ->action(function ($record) {
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

                                // ✅ Cek pivot ada atau belum untuk hindari masalah updateOrInsert
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
                                            'id_lahan'                     => $record->id_lahan,
                                            'id_produksi'                  => null,
                                            'jumlah_batang'                => max(0, $totalBatang),
                                            'kubikasi'                     => max(0, $kubikasi),
                                            'diserahkan_oleh'              => Auth::user()->name,
                                            'diterima_oleh'                => '-',
                                            'tipe'                         => 'lahan_rotary',
                                            'status'                       => 'Lahan Siap',
                                            'created_at'                   => now(),
                                            'updated_at'                   => now(),
                                        ]);
                                }

                                // ✅ Update semua row tempat_kayus dengan id_lahan yang sama
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
                    ->label('Terima Kayu')
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
                        if (!$bisaTerima) return false;

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
                                        'status'        => 'Sudah Diterima',
                                        'updated_at'    => now(),
                                    ]);

                                // ✅ Update semua row tempat_kayus dengan id_lahan yang sama
                                DB::table('tempat_kayus')
                                    ->where('id_lahan', $record->id_lahan)
                                    ->update([
                                        'diterima_oleh' => Auth::user()->name,
                                        'status'        => 'sudah diterima',
                                        'updated_at'    => now(),
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
                                'code'    => $e->getCode(),
                                'trace'   => $e->getTraceAsString(),
                            ]);

                            Notification::make()
                                ->title('Gagal menerima kayu')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible($isAdmin),
                ]),
            ]);
    }
}
