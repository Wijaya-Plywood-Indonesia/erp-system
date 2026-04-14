<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries\Tables;

use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PenggunaanLahanRotariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lahan_display')
                    ->label('Lahan')
                    ->getStateUsing(
                        fn($record) =>
                        "{$record->lahan->kode_lahan} - {$record->lahan->nama_lahan}"
                    )
                    ->sortable(query: function ($query, string $direction) {
                        $query->join('lahans', 'penggunaan_lahan_rotaries.id_lahan', '=', 'lahans.id')
                            ->orderBy('lahans.kode_lahan', $direction)
                            ->select('penggunaan_lahan_rotaries.*');
                    }) // optional
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('lahan', function ($q) use ($search) {
                            $q->where('kode_lahan', 'like', "%{$search}%")
                                ->orWhere('nama_lahan', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->placeholder('Belum Daftar Jenis Kayu'),

                TextColumn::make('jumlah_batang')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(), // 👈 ini yang munculkan tombol "Tambah"
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('lahan_selesai')
                    ->label('Lahan Selesai')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Tandai Lahan Selesai?')
                    ->modalDescription(
                        fn($record) =>
                        "Stok kayu dari lahan {$record->lahan->kode_lahan} - {$record->lahan->nama_lahan} " .
                            "({$record->jenisKayu?->nama_kayu}) akan dicatat keluar dan jumlah batang " .
                            "di penggunaan lahan ini akan diupdate sesuai stok."
                    )
                    ->modalSubmitActionLabel('Ya, Selesaikan')
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {

                            $idLahan     = $record->id_lahan;
                            $idJenisKayu = $record->id_jenis_kayu;

                            $stoks = HppAverageSummarie::where('id_lahan', $idLahan)
                                ->where('id_jenis_kayu', $idJenisKayu)
                                ->where('stok_batang', '>', 0)
                                ->get();

                            if ($stoks->isEmpty()) {
                                Notification::make()
                                    ->title('Tidak ada stok yang tersedia')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $totalBatang   = $stoks->sum('stok_batang');

                            // ✅ Update hanya jumlah batang di penggunaan lahan
                            $record->update([
                                'jumlah_batang' => $totalBatang,
                            ]);
                        });

                        Notification::make()
                            ->title('Lahan berhasil diselesaikan')
                            ->body('Stok belum dikurangi, hanya dicatat.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
