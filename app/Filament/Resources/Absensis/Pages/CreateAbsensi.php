<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Models\DetailAbsensi;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class CreateAbsensi extends CreateRecord
{
    protected static string $resource = AbsensiResource::class;

    /**
     * Logic yang dijalankan setelah data Absensi (Header) berhasil disimpan.
     */
    protected function afterCreate(): void
    {
        // 1. Ambil data record (file_path dan tanggal yang dipilih di form)
        $record = $this->record;
        $targetDate = Carbon::parse($record->tanggal)->format('Y-m-d');

        // 2. Pastikan file ada di storage
        if (!Storage::disk('public')->exists($record->file_path)) {
            Notification::make()
                ->danger()
                ->title('Gagal memproses file')
                ->body('File log absensi tidak ditemukan di storage.')
                ->send();
            return;
        }

        // 3. Baca isi file
        $fileContent = Storage::disk('public')->get($record->file_path);

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $fileContent));

        $rawLogs = [];
        $processedCount = 0;

        // 4. Proses baris demi baris dari file TXT/DAT
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) continue;

            // Pecah kolom berdasarkan spasi atau tab
            $parts = preg_split('/\s+/', $trimmedLine);

            if (count($parts) >= 8) {
                $empCode = ltrim($parts[2], '0'); // Bersihkan nol di depan
                $dateInFile = str_replace('/', '-', $parts[6]); // Ubah / jadi -
                $timeInFile = $parts[7];

                if ($dateInFile === $targetDate) {
                    $rawLogs[$empCode][] = $timeInFile;
                    $processedCount++;
                }
            }
        }

        foreach ($rawLogs as $empCode => $times) {
            $uniqueTimes = array_unique($times);

            sort($uniqueTimes);

            $jamMasuk = $uniqueTimes[0];

            $jamPulang = (count($uniqueTimes) > 1) ? end($uniqueTimes) : null;

            DetailAbsensi::updateOrCreate(
                [
                    'kode_pegawai' => $empCode,
                    'tanggal'      => $targetDate,
                ],
                [
                    'id_absensi'   => $record->id, // ID dari tabel header absensis
                    'jam_masuk'    => $jamMasuk,
                    'jam_pulang'   => $jamPulang,
                ]
            );
        }

        // 7. Berikan laporan hasil proses
        Notification::make()
            ->success()
            ->title('Proses Selesai')
            ->body("Berhasil mengolah $processedCount baris log untuk " . count($rawLogs) . " pegawai.")
            ->send();
    }

    /**
     * Alihkan halaman kembali ke daftar absensi setelah selesai
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
