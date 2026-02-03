<?php

namespace App\Filament\Resources\NotaKayus\Tables;

use App\Models\DetailKayuMasuk;
use App\Models\DetailTurusanKayu;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class NotaKayusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_nota')
                    ->searchable(),
                TextColumn::make('info_kayu')
                    ->label('Info Kayu')
                    ->sortable()
                    ->searchable(query: function ($query, string $search) {
                        // Menghapus SEMUA karakter selain angka (termasuk spasi)
                        // "Seri 2" -> "2" | "Seri2" -> "2" | "Seri   2" -> "2"
                        $numberOnly = preg_replace('/[^0-9]/', '', $search);

                        return $query->whereHas('kayuMasuk', function ($q) use ($search, $numberOnly) {
                            // Cek apakah hasil pembersihan menghasilkan angka
                            if (is_numeric($numberOnly) && $numberOnly !== '') {
                                // Gunakan '=' untuk hasil EKSAK agar Seri 12 atau 22 tidak ikut muncul
                                $q->where('seri', '=', $numberOnly);
                            } else {
                                // Jika mencari nama supplier
                                $q->whereHas('penggunaanSupplier', function ($sq) use ($search) {
                                    $sq->where('nama_supplier', 'like', "%{$search}%");
                                });
                            }
                        });
                    })
                    ->getStateUsing(function ($record) {
                        if (!$record->kayuMasuk) return '-';
                        $seri = $record->kayuMasuk->seri ?? '-';
                        $namaSupplier = $record->kayuMasuk->penggunaanSupplier?->nama_supplier ?? '-';
                        $noTelepon = $record->kayuMasuk->penggunaanSupplier?->no_telepon ?? '-';

                        return "Seri {$seri} - {$namaSupplier} ({$noTelepon})";
                    }),

                TextColumn::make('penanggung_jawab')
                    ->label('PJ')
                    ->searchable(),

                //Dari Turusan ROmbongan
                TextColumn::make('total_summary2')
                    ->label('Rekap Turusan 1')
                    ->getStateUsing(function ($record) {
                        if (!$record->kayuMasuk) {
                            return "0 Batang\n0.0000 m³";
                        }

                        $total = DetailKayuMasuk::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                        $batang = number_format($total['total_batang']);
                        $kubikasi = number_format($total['total_kubikasi'], 4);

                        return "{$batang} Batang\n{$kubikasi} m³";
                    })
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn(string $state) => str_replace("\n", '<br>', e($state)))
                    ->html() // penting agar <br> terbaca sebagai baris baru
                    ->alignCenter(),
                //dari turusan manual
                TextColumn::make('total_summary')
                    ->label('Rekap Turusan 2')
                    ->getStateUsing(function ($record) {
                        if (!$record->kayuMasuk) {
                            return "0 Batang\n0.0000 m³";
                        }

                        $total = DetailTurusanKayu::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                        $batang = number_format($total['total_batang']);
                        $kubikasi = number_format($total['total_kubikasi'], 4);

                        return "{$batang} Batang\n{$kubikasi} m³";
                    })
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn(string $state) => str_replace("\n", '<br>', e($state)))
                    ->html() // penting agar <br> terbaca sebagai baris baru
                    ->alignCenter(),

                TextColumn::make('penerima')
                    ->searchable(),
                TextColumn::make('satpam')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->searchable()
                    ->badge() // ubah jadi badge
                    ->colors([
                        'secondary' => 'Belum Diperiksa',
                        'success' => fn($state) => str_contains($state, 'Sudah Diperiksa'),
                        'warning' => fn($state) => str_contains($state, 'Menunggu'),
                        'danger' => fn($state) => str_contains($state, 'Ditolak'),
                    ]),


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
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('cek')
                    ->label('Tandai Sudah Diperiksa')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(function ($record) {
                        // Pastikan status masih "Belum Diperiksa"
                        if ($record->status !== 'Belum Diperiksa') {
                            return false;
                        }

                        // Pastikan ada relasi kayuMasuk
                        if (!$record->kayuMasuk) {
                            return false;
                        }

                        // Ambil total dari kedua sumber
                        $total1 = DetailTurusanKayu::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                        $total2 = DetailKayuMasuk::hitungTotalByKayuMasuk($record->kayuMasuk->id);

                        // Bandingkan total batang dan kubikasi
                        $batangSama = $total1['total_batang'] == $total2['total_batang'];
                        $kubikasiSama = abs($total1['total_kubikasi'] - $total2['total_kubikasi']) < 0.0001; // toleransi desimal

                        return $batangSama && $kubikasiSama;
                    })
                    ->action(function ($record) {
                        $user = Auth::user();
                        $record->status = "Sudah Diperiksa oleh {$user->name}";
                        $record->save();
                    })
                    ->requiresConfirmation()
                    ->successNotificationTitle('Status berhasil diperbarui'),

                // ACTION CETAK NOTA (Existing)
                Action::make('print')
                    ->label('Cetak Nota')
                    ->icon('heroicon-o-printer')
                    ->color('green')
                    ->url(fn($record) => route('nota-kayu.show', $record))
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record->status !== 'Belum Diperiksa') // tombol hanya muncul jika sudah diperiksa
                    ->disabled(
                        fn($record) =>
                        !$record->kayuMasuk?->detailTurusanKayus()->exists() // tetap pakai logika disable sebelumnya
                    ),

                // ACTION CETAK TURUS (Baru)
                Action::make('print_turus')
                    ->label('Cetak Turus')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info') // Warna biru untuk membedakan dengan Nota
                    // Asumsi route-nya bernama 'nota-kayu.turus', arahkan ke controller Turus yang baru dibuat
                    ->url(fn($record) => route('nota-kayu.turus', $record))
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record->status !== 'Belum Diperiksa') // Muncul hanya jika sudah disetujui
                    ->disabled(
                        fn($record) =>
                        !$record->kayuMasuk?->detailTurusanKayus()->exists()
                    ),

                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //      DeleteBulkAction::make(),
                // ]),
            ])
            ->filters([
                SelectFilter::make('seri')
                    ->relationship('kayuMasuk', 'seri')
                    ->searchable()
                    ->label('Pilih Seri'),
            ]);
    }
}
