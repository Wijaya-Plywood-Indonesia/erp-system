<?php

namespace App\Filament\Resources\Absensis\RelationManagers;

use App\Filament\Pages\Absen;
use App\Models\DetailAbsensi;
use App\Models\Pegawai;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;

class DetailAbsensiRelationManager extends RelationManager
{
    protected static string $relationship = 'detailAbsensis';

    /**
     * Kita kosongkan Form karena data ini bersifat Read-Only 
     * hasil dari parsing file mesin finger.
     */
    public function form(Schema $schema): Schema
    {
        return $schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('kode_pegawai')
            ->columns([

                TextColumn::make('tanggal')
                    ->label('Tanggal Absen')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('kode_pegawai')
                    ->label('Kode Pegawai')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->time('H:i:s')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->time('H:i:s')
                    ->badge()
                    ->color('danger')
                    ->placeholder('-- : -- : --') // Tampil jika data pulang tidak ada
                    ->sortable(),
            ])
            ->filters([
                // Anda bisa menambahkan filter di sini jika diperlukan
            ])
            ->headerActions([
                // Header action dikosongkan karena tidak boleh input manual
            ])
            ->actions([
                // Kita tidak berikan EditAction karena bersifat read-only
            ])
            ->headerActions([
                Action::make('sync_to_report')
                    ->label('Sync ke Laporan')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('success')
                    ->action(function () {
                        // 1. Tentukan tanggal target yang mau di-sync (misal hari ini)
                        $targetDate = now()->format('Y-m-d');
                        $nextDate   = Carbon::parse($targetDate)->addDay()->format('Y-m-d');
                        $prevDate   = Carbon::parse($targetDate)->subDay()->format('Y-m-d');
                        $absensiharini = DetailAbsensi::where('tanggal', $targetDate)->get();

                        $fixedCount = 0;

                        foreach ($absensiharini as $absen) {
                            $empCode = $absen->kode_pegawai;
                            $pegawai = Pegawai::where('kode_pegawai', $empCode)->first();
                            if (!$pegawai) continue;

                            $jadwalMasuk  = $pegawai->jam_masuk_sistem ?? '07:00:00';
                            $jadwalPulang = $pegawai->jam_pulang_sistem ?? '16:00:00';
                            $isShiftMalamSistem = Carbon::parse($jadwalMasuk)->hour >= 14;
                            if ($isShiftMalamSistem && $absen->jam_masuk && empty($absen->jam_pulang)) {
                                $logBesok = DetailAbsensi::where('kode_pegawai', $empCode)
                                    ->where('tanggal', $nextDate)
                                    ->first();
                                if ($logBesok && $logBesok->jam_masuk && Carbon::parse($logBesok->jam_masuk)->hour <= 10) {
                                    $absen->jam_pulang = $logBesok->jam_masuk;
                                    $absen->save();
                                    $fixedCount++;
                                }
                            }
                            if ($isShiftMalamSistem && $absen->jam_masuk && empty($absen->jam_pulang)) {
                                $jamMasukCarbon = Carbon::parse($absen->jam_masuk);

                                // Jika jam masuknya ternyata jam 00:00 s.d 09:00 Pagi, ini FIX jam pulang shift malam kemarin!
                                if ($jamMasukCarbon->hour >= 0 && $jamMasukCarbon->hour <= 9) {
                                    // Pindahkan ke kolom jam_pulang di TANGGAL KEMARIN (prevDate)
                                    DetailAbsensi::updateOrCreate(
                                        ['kode_pegawai' => $empCode, 'tanggal' => $prevDate],
                                        ['jam_pulang' => $absen->jam_masuk]
                                    );

                                    // Hapus data "jam masuk palsu" di hari ini agar tidak merusak laporan hari ini
                                    $absen->jam_masuk = null;
                                    $absen->save();
                                    $fixedCount++;
                                }
                            }

                            // KASUS C: Shift Normal/Pagi tapi Jam Terbalik karena salah kolom di file
                            if ($isShiftMalamSistem && $absen->jam_masuk && $absen->jam_pulang) {
                                $jamMasukHour  = Carbon::parse($absen->jam_masuk)->hour;
                                $jamPulangHour = Carbon::parse($absen->jam_pulang)->hour;

                                // Jika jam masuk terdeteksi pagi (00-12) dan jam pulang sore/malam (12-23)
                                // berarti posisinya terbalik → swap
                                if ($jamMasukHour <= 12 && $jamPulangHour >= 12) {
                                    $temp              = $absen->jam_masuk;
                                    $absen->jam_masuk  = $absen->jam_pulang;
                                    $absen->jam_pulang = $temp;
                                    $absen->save();
                                    $fixedCount++;
                                }
                            }
                        }

                        // 4. Berikan notifikasi hasil pembersihan data ke user
                        Notification::make()
                            ->success()
                            ->title('Sinkronisasi Berhasil')
                            ->body("Laporan diperbarui. Berhasil memperbaiki $fixedCount data shift yang janggal/terbalik.")
                            ->send();

                        // 5. Alihkan ke halaman tujuan dengan aman
                        // Ganti 'Absen' dengan class Custom Page atau Resource tujuan Anda yang tepat
                        return redirect()->to(Absen::getUrl(['tanggal' => $targetDate]));
                    })
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Hanya berikan Delete jika ingin menghapus data yang salah import
                    DeleteBulkAction::make(),
                    // Opsional: Jika menggunakan filament-excel
                    // ExportBulkAction::make(), 
                ]),
            ]);
    }
}
