<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Models\DetailAbsensi;
<<<<<<< HEAD
use App\Models\Pegawai;
=======
use App\Services\AbsensiParsingService;
>>>>>>> 829a8f5264abddd12f4c71777bd5cb353244f002
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateAbsensi extends CreateRecord
{
    protected static string $resource = AbsensiResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        $targetDate = Carbon::parse($record->tanggal)->format('Y-m-d');
<<<<<<< HEAD

        // Batas toleransi pengambilan data log esok hari untuk cover pulang Shift Malam
        $nextDate   = Carbon::parse($record->tanggal)->addDay()->format('Y-m-d');

        $files = $record->file_path;
        if (empty($files) || !is_array($files)) return;

        // Wadah tunggal raksasa untuk menggabungkan data seluruh kantor (Kantor A, B, dst)
        $rawLogs        = [];
        $totalProcessed = 0;

        // ================================================
        // STEP 1: PARSING — Gabungkan Semua Log Multi-File (TXT / DAT)
        // ================================================
        foreach ($files as $file) {
            if (!Storage::disk('public')->exists($file)) continue;

            $fileContent = Storage::disk('public')->get($file);

            // Bersihkan karakter BOM tersembunyi jika ada di file TXT
            $fileContent = str_replace("\xEF\xBB\xBF", '', $fileContent);
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $fileContent));

            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                // Abaikan baris kosong atau baris header tabel
                if (empty($trimmedLine) || str_contains($trimmedLine, 'DateTime') || str_contains($trimmedLine, 'Kodep')) {
                    continue;
                }

                // Pecah berdasarkan TAB (\t) dahulu agar nama yang mengandung spasi tidak pecah. 
                // Jika tidak ada TAB, baru pecah berdasarkan spasi reguler.
                if (str_contains($trimmedLine, "\t")) {
                    $parts = explode("\t", $trimmedLine);
                } else {
                    $parts = preg_split('/\s+/', $trimmedLine);
                }

                // Bersihkan spasi sisa di setiap elemen dan rapikan kembali urutan indeks array-nya
                $parts = array_values(array_filter(array_map('trim', $parts)));

                // CARI POSISI KOLOM TANGGAL SECARA DINAMIS
                $dateIndex = null;
                foreach ($parts as $i => $value) {
                    if (preg_match('/\d{4}[\/\-]\d{2}[\/\-]\d{2}/', $value)) {
                        $dateIndex = $i;
                        break;
                    }
                }

                // Jika baris ini tidak mengandung format tanggal, skip
                if ($dateIndex === null) continue;

                try {
                    // Ekstrak string Datetime (Mendukung tanggal & jam gabung maupun terpisah)
                    $dateTimeString = $parts[$dateIndex];
                    if (isset($parts[$dateIndex + 1]) && preg_match('/^\d{2}[\:\.]\d{2}/', $parts[$dateIndex + 1])) {
                        $dateTimeString .= ' ' . $parts[$dateIndex + 1];
                    }

                    $carbonLog = Carbon::parse(str_replace('/', '-', $dateTimeString));
                    $dateStr   = $carbonLog->format('Y-m-d');
                    $timeStr   = $carbonLog->format('H:i:s');

                    // Filter Tanggal: Hanya proses data tanggal target dan keesokan harinya
                    if (!in_array($dateStr, [$targetDate, $nextDate])) continue;

                    // AMBIL KODE PEGAWAI (Ambil 4 Angka Terakhir secara absolut dari Kolom EnNo)
                    $empCode = null;

                    // Strategi Utama: Berdasarkan berkas GLogData, EnNo berada di indeks ke-2
                    if (isset($parts[2]) && is_numeric($parts[2]) && strlen($parts[2]) >= 4) {
                        $fourDigits = substr($parts[2], -4); // Potong ambil 4 angka terakhir
                        $empCode    = ltrim($fourDigits, '0'); // Bersihkan nol di depan
                    } else {
                        // Fallback: Jika indeks bergeser, sisir elemen angka di awal baris sebelum kolom tanggal
                        foreach ($parts as $k => $part) {
                            if ($k >= $dateIndex) break;
                            if (is_numeric($part) && strlen($part) >= 4) {
                                $fourDigits = substr($part, -4);
                                $empCode    = ltrim($fourDigits, '0');
                            }
                        }
                    }

                    // Pastikan Kode Pegawai, Jam, dan format kodenya murni angka
                    if (!$empCode || !$timeStr || !is_numeric($empCode)) continue;

                    // Masukkan ke wadah merge gabungan multi-kantor berdasarkan kode pegawai yang sama
                    $rawLogs[$empCode][] = [
                        'date' => $dateStr,
                        'time' => $timeStr,
                        'full' => $carbonLog,
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }
=======
        $nextDate = Carbon::parse($record->tanggal)->addDay()->format('Y-m-d');

        $files = $record->file_path;
        if (empty($files) || !is_array($files)) {
            return;
>>>>>>> 829a8f5264abddd12f4c71777bd5cb353244f002
        }

        /** @var AbsensiParsingService $parsingService */
        $parsingService = app(AbsensiParsingService::class);
        $parseResult = $parsingService->collectLogs($files, $targetDate, $nextDate);

        $rawLogs = $parseResult['raw_logs'];
        $skippedLines = $parseResult['skipped_lines'];
        $totalProcessed = 0;

        // ================================================
        // STEP 2: PROSES MERGE — Kelompokkan Berdasarkan Kedekatan Shift Master
        // ================================================
        foreach ($rawLogs as $empCode => $entries) {
            // Urutkan semua log gabungan (Kantor A + Kantor B) secara kronologis dari pagi ke malam
            $sorted = collect($entries)->sortBy('full');

            // Ambil acuan aturan shift resmi milik pegawai dari database master
            $pegawai = Pegawai::where('kode_pegawai', $empCode)->first();

            // Set default jadwal kerja normal/pagi jika master data kosong
            $jadwalMasuk  = $pegawai->jam_masuk_sistem ?? '07:00:00';
            $jadwalPulang = $pegawai->jam_pulang_sistem ?? '16:00:00';

            // Deteksi apakah sistem mengonfigurasi pegawai ini sebagai SHIFT MALAM
            $isShiftMalamSistem = Carbon::parse($jadwalMasuk)->hour >= 14;

            $jamMasukLog  = null;
            $jamPulangLog = null;

            $minSelisihMasuk  = 999999;
            $minSelisihPulang = 999999;

            foreach ($sorted as $entry) {
                $logTime = Carbon::parse($entry['full']);

                // Proyeksikan waktu kerja ideal di database pada tanggal target
                $targetMasukDt  = Carbon::parse("$targetDate $jadwalMasuk");
                $targetPulangDt = $isShiftMalamSistem
                    ? Carbon::parse("$targetDate $jadwalPulang")->addDay() // Shift malam pulang di esok pagi hari
                    : Carbon::parse("$targetDate $jadwalPulang");

                // Hitung kedekatan (selisih menit absolut) waktu tap log terhadap waktu ideal shift
                $selisihKeMasuk  = abs($logTime->diffInMinutes($targetMasukDt));
                $selisihKePulang = abs($logTime->diffInMinutes($targetPulangDt));

                // PILIH STRATEGI TERDEKAT (Mencegah Tertukar/Terbalik)
                if ($selisihKeMasuk < $selisihKePulang) {
                    // Log ini dinilai paling masuk akal sebagai JAM MASUK
                    // Toleransi maksimal keterlambatan/kecepatan tap adalah 8 jam (480 menit)
                    if ($selisihKeMasuk < $minSelisihMasuk && $selisihKeMasuk < 480) {
                        $minSelisihMasuk = $selisihKeMasuk;
                        $jamMasukLog     = $entry['time'];
                    }
                } else {
                    // Log ini dinilai paling masuk akal sebagai JAM PULANG
                    if ($selisihKePulang < $minSelisihPulang && $selisihKePulang < 480) {
                        $minSelisihPulang = $selisihKePulang;
                        $jamPulangLog      = $entry['time'];
                    }
                }
            }

            // ================================================
            // STEP 3: SIMPAN HASIL SINKRONISASI KE DATABASE
            // ================================================
            if ($jamMasukLog || $jamPulangLog) {
                // updateOrCreate memastikan jika data tanggal tersebut sudah ada akan diperbarui, jika belum ada akan dibuat baru
                DetailAbsensi::updateOrCreate(
                    ['kode_pegawai' => $empCode, 'tanggal' => $targetDate],
                    [
                        'id_absensi' => $record->id,
                        'jam_masuk'  => $jamMasukLog,
                        'jam_pulang' => $jamPulangLog,
                    ]
                );
                $totalProcessed++;
            }
        }

        // Kirimkan notifikasi keberhasilan di Filament v4
        Notification::make()
            ->success()
            ->title('Import Berhasil')
<<<<<<< HEAD
            ->body("Berhasil memproses & menyatukan $totalProcessed data absensi pegawai.")
=======
            ->body("Berhasil memproses $totalProcessed data pegawai, {$skippedLines} baris dilewati karena format tidak valid.")
>>>>>>> 829a8f5264abddd12f4c71777bd5cb353244f002
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
