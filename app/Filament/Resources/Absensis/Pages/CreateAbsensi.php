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
     * Logic yang dijalankan setelah data Absensi (Parent) berhasil disimpan.
     */
    protected function afterCreate(): void
    {
        // 1. Ambil data record yang baru saja dibuat
        $record = $this->record;

        // Pastikan format tanggal seragam (YYYY-MM-DD)
        $targetDate = Carbon::parse($record->tanggal)->format('Y-m-d');

        // 2. Cek apakah file benar-benar ada di disk public
        if (!Storage::disk('public')->exists($record->file_path)) {
            Notification::make()
                ->danger()
                ->title('Gagal membaca file')
                ->body('File log tidak ditemukan di storage.')
                ->send();
            return;
        }

        // 3. Baca konten file (.dat atau .txt)
        $fileContent = Storage::disk('public')->get($record->file_path);

        // Bersihkan karakter baris baru agar kompatibel antara Windows/Linux
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $fileContent));

        $collectedLogs = [];
        $rowCount = 0;

        // 4. Proses baris demi baris
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) continue;

            /** * Menggunakan Regex untuk memecah kolom.
             * Memisahkan berdasarkan spasi atau tab.
             */
            $parts = preg_split('/\s+/', $trimmedLine);

            /**
             * LOGIKA PARSING BERDASARKAN FORMAT:
             * parts[2] = Kode Pegawai (misal: 000004204)
             * parts[6] = Tanggal (misal: 2024/01/28)
             * parts[7] = Jam (misal: 14:31:11)
             */
            if (count($parts) >= 8) {
                // --- PERBAIKAN FORMAT KODE PEGAWAI ---
                // ltrim menghapus angka 0 di depan agar '000003122' menjadi '3122'
                $empCode = ltrim($parts[2], '0');

                // Mengubah format 2024/01/28 menjadi 2024-01-28 agar cocok dengan DB
                $dateInFile = str_replace('/', '-', $parts[6]);
                $timeInFile = $parts[7];

                // Cek apakah data di file sesuai dengan tanggal yang kita pilih di Form
                if ($dateInFile === $targetDate) {
                    $collectedLogs[$empCode][] = $timeInFile;
                    $rowCount++;
                }
            }
        }

        // 5. Simpan hasil olahan ke tabel DetailAbsensi
        foreach ($collectedLogs as $empCode => $times) {
            // Urutkan jam dari yang paling awal (pagi) ke paling akhir (sore)
            sort($times);

            $jamMasuk = $times[0];

            /**
             * Jika scan lebih dari 1 kali, maka jam terakhir dianggap Jam Pulang.
             * Jika hanya 1 kali scan, Jam Pulang dikosongkan (null).
             */
            $jamPulang = (count($times) > 1) ? end($times) : null;

            // Menggunakan updateOrCreate untuk menghindari duplikasi jika upload ulang
            DetailAbsensi::updateOrCreate(
                [
                    'id_absensi'   => $record->id,
                    'kode_pegawai' => $empCode,
                    'tanggal'      => $targetDate,
                ],
                [
                    'jam_masuk'    => $jamMasuk,
                    'jam_pulang'   => $jamPulang,
                ]
            );
        }

        // Berikan notifikasi sukses kepada user
        Notification::make()
            ->success()
            ->title('Sinkronisasi Berhasil')
            ->body("Berhasil memproses $rowCount baris data untuk kode pegawai.")
            ->send();
    }

    /**
     * Redirect kembali ke list setelah berhasil membuat data
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
