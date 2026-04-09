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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TempatKayusTable
{
    private const ROLE_GRADER   = ['Grader Kayu 1', 'Grader Kayu 2'];
    private const ROLE_PENGAWAS = ['pengawas_rotary_1', 'pengawas_rotary_2'];
    private const ROLE_ADMIN    = ['super_admin', 'Super Admin'];

    /**
     * CATATAN: MESIN_MAP telah dihapus agar semua mesin dapat menerima kayu 
     * tanpa batasan panjang (130/260).
     */

    public static function configure(Table $table): Table
    {
        $user = Auth::user();

        $isGrader   = $user->hasAnyRole(self::ROLE_GRADER);
        $isPengawas = $user->hasAnyRole(self::ROLE_PENGAWAS);
        $isAdmin    = $user->hasAnyRole(self::ROLE_ADMIN);

        $bisaSerah  = $isGrader  || $isAdmin;
        $bisaTerima = $isPengawas || $isAdmin;

        return $table
            ->columns([
                TextColumn::make('lahan.kode_lahan')
                    ->label('Kode Lahan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('lahan.nama_lahan')
                    ->label('Nama Lahan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('kayuMasuk.seri')
                    ->label('Seri Kayu')
                    ->formatStateUsing(fn($state) => 'Seri - ' . ($state ?? '-'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('jumlah_batang')
                    ->label('Jumlah Batang')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('kubikasi')
                    ->label('Kubikasi (m³)')
                    ->getStateUsing(
                        fn($record) =>
                        number_format(
                            HppAverageSummarie::where('id_lahan', $record->id_lahan)
                                ->sum('stok_kubikasi'),
                            4
                        )
                    ),

                TextColumn::make('panjang_kayu')
                    ->label('Panjang (cm)')
                    ->getStateUsing(
                        fn($record) =>
                        HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->value('panjang') ?? '-'
                    )
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state == 130 => 'info',
                        $state == 260 => 'success',
                        default       => 'gray',
                    }),

                TextColumn::make('diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->default('-'),

                TextColumn::make('diterima_oleh')
                    ->label('Diterima Oleh')
                    ->default('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'sudah diserahkan' => 'Sudah Diserahkan',
                        'sudah diterima'   => 'Sudah Diterima',
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

                // TOMBOL SERAH
                Action::make('serah_kayu')
                    ->label('Serah Kayu')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Kayu?')
                    ->modalDescription(
                        fn($record) =>
                        "Kayu dari lahan {$record->lahan?->kode_lahan} " .
                            "({$record->jumlah_batang} batang) akan diserahkan ke rotary."
                    )
                    ->modalSubmitActionLabel('Ya, Serahkan')
                    ->visible(function ($record) use ($bisaSerah, $isAdmin) {
                        if (!$bisaSerah) return false;
                        if ($isAdmin) return true;
                        return $record->status === 'belum serah' || $record->status === null;
                    })
                    ->action(function ($record) {
                        $idLahan  = $record->id_lahan;
                        $kubikasi = HppAverageSummarie::where('id_lahan', $idLahan)->sum('stok_kubikasi');

                        try {
                            // Update atau Insert ke pivot serah terima
                            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')->updateOrInsert(
                                [
                                    'id_lahan' => $idLahan,
                                    'tipe'     => 'lahan_rotary',
                                ],
                                [
                                    'id_detail_hasil_palet_rotary' => null,
                                    'id_produksi'     => null,
                                    'jumlah_batang'   => $record->jumlah_batang,
                                    'kubikasi'        => $kubikasi,
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh'   => '-',
                                    'status'          => 'Lahan Siap',
                                    'updated_at'      => now(),
                                    'created_at'      => now(),
                                ]
                            );

                            $record->update([
                                'diserahkan_oleh' => Auth::user()->name,
                                'diterima_oleh'   => null,
                                'status'          => 'sudah diserahkan',
                            ]);

                            Notification::make()->title('Kayu berhasil diserahkan')->success()->send();
                        } catch (\Throwable $e) {
                            Log::error('Serah Kayu FAILED: ' . $e->getMessage());
                            Notification::make()->title('Gagal menyerahkan kayu')->danger()->send();
                        }
                    }),

                // TOMBOL TERIMA
                Action::make('terima_kayu')
                    ->label('Terima Kayu')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Terima Kayu dari Grader?')
                    ->modalDescription(
                        fn($record) =>
                        "Kayu dari lahan {$record->lahan?->kode_lahan} " .
                            "({$record->jumlah_batang} batang) akan diterima tanpa batasan mesin."
                    )
                    ->modalSubmitActionLabel('Ya, Terima')
                    ->visible(fn($record) => $bisaTerima && $record->status === 'sudah diserahkan')
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

                                $record->update([
                                    'diterima_oleh' => Auth::user()->name,
                                    'status'        => 'sudah diterima',
                                ]);
                            });

                            Notification::make()->title('Kayu berhasil diterima')->success()->send();
                        } catch (\Throwable $e) {
                            Log::error('Terima Kayu FAILED: ' . $e->getMessage());
                            Notification::make()->title('Gagal menerima kayu')->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible($isGrader || $isAdmin),
                ]),
            ]);
    }
}
