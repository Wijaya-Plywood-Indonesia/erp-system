<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Models\DetailAbsensi;
use App\Models\Pegawai;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CreateAbsensi extends CreateRecord
{
    protected static string $resource = AbsensiResource::class;

    protected function afterCreate(): void
    {
        $record     = $this->record;
        $targetDate = Carbon::parse($record->tanggal)->format('Y-m-d');

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
        }

        // ================================================
        // STEP 2: PROSES MERGE — Kelompokkan Berdasarkan Kedekatan Shift Master
        // ================================================
        foreach ($rawLogs as $empCode => $entries) {
            // Urutkan semua log gabungan (Kantor A + Kantor B) secara kronologis dari pagi ke malam
            $sorted = collect($entries)->sortBy('full');

            // Ambil acuan aturan shift resmi milik pegawai dari database master
            $pegawai = Pegawai::where('kode_pegawai', $empCode)->first();

            // -----------------------------------------------------------------
            // 1️⃣ Ambil jadwal master (jika ada) dan deteksi shift dari tabel produksi
            // -----------------------------------------------------------------
            // Default jadwal (fallback bila tidak ada data master)
            $jadwalMasuk  = '07:00:00';
            $jadwalPulang = '16:00:00';
            $isShiftMalamSistem = false;
            $forcedShift = null; // nilai dari tabel produksi, jika ada

            if ($pegawai) {
                $jadwalMasuk  = $pegawai->jam_masuk_sistem ?? $jadwalMasuk;
                $jadwalPulang = $pegawai->jam_pulang_sistem ?? $jadwalPulang;
                $isShiftMalamSistem = Carbon::parse($jadwalMasuk)->hour >= 14;

                // ------- CARI SHIFT DI TABEL PRODUKSI (Dryer, HP, Sanding, Graji) -------
                $dryerShift = \App\Models\ProduksiPressDryer::whereDate('tanggal_produksi', $targetDate)
                    ->whereHas('detailPegawais', fn($q) => $q->where('id_pegawai', $pegawai->id))
                    ->value('shift');
                $hpShift = \App\Models\ProduksiHp::whereDate('tanggal_produksi', $targetDate)
                    ->whereHas('detailPegawaiHp', fn($q) => $q->where('id_pegawai', $pegawai->id))
                    ->value('shift');
                $sandingShift = \App\Models\ProduksiSanding::whereDate('tanggal', $targetDate)
                    ->whereHas('pegawaiSandings', fn($q) => $q->where('id_pegawai', $pegawai->id))
                    ->value('shift');
                $grajiShift = \App\Models\ProduksiGrajitriplek::whereDate('tanggal_produksi', $targetDate)
                    ->whereHas('pegawaiGrajiTriplek', fn($q) => $q->where('id_pegawai', $pegawai->id))
                    ->value('shift');

                $shifts = array_filter([$dryerShift, $hpShift, $sandingShift, $grajiShift]);
                if (count($shifts) > 0) {
                    // Ambil nilai pertama yang tidak null; semua yang tidak null seharusnya sama.
                    $forcedShift = strtoupper(trim(reset($shifts)));
                    // Jika ada perbedaan antar tabel, catat warning.
                    if (count(array_unique($shifts)) > 1) {
                        Log::warning('Conflicting shift values for employee ' . $empCode . ' on ' . $targetDate, [
                            'dryer'   => $dryerShift,
                            'hp'      => $hpShift,
                            'sanding' => $sandingShift,
                            'graji'   => $grajiShift,
                        ]);
                    }
                }
            }

            // -----------------------------------------------------------------
            // 2️⃣ Tentukan apakah pegawai ini seharusnya diperlakukan sebagai SHIFT MALAM
            // -----------------------------------------------------------------
            // Prioritas: forcedShift (dari produksi) > master schedule > default
            if ($forcedShift === 'MALAM') {
                $isShiftMalam = true;
            } elseif ($forcedShift === 'PAGI') {
                $isShiftMalam = false;
            } else {
                $isShiftMalam = $isShiftMalamSistem;
            }

            // -----------------------------------------------------------------
            // 3️⃣ Proses logs untuk menentukan jam masuk & jam pulang (versi final)
            // -----------------------------------------------------------------
            $jamMasukLog  = null;
            $jamPulangLog = null;
            $minSelisihMasuk  = PHP_INT_MAX;
            $minSelisihPulang = PHP_INT_MAX;

            foreach ($sorted as $entry) {
                /** @var Carbon $logTime */
                $logTime = $entry['full'];

                $targetMasukDt  = Carbon::parse("$targetDate $jadwalMasuk");
                $targetPulangDt = $isShiftMalam
                    ? Carbon::parse("$targetDate $jadwalPulang")->addDay()
                    : Carbon::parse("$targetDate $jadwalPulang");

                $selisihKeMasuk  = abs($logTime->diffInMinutes($targetMasukDt));
                $selisihKePulang = abs($logTime->diffInMinutes($targetPulangDt));

                if ($selisihKeMasuk < $selisihKePulang) {
                    if ($selisihKeMasuk < $minSelisihMasuk && $selisihKeMasuk < 480) {
                        $minSelisihMasuk = $selisihKeMasuk;
                        $jamMasukLog    = $entry['time'];
                    }
                } else {
                    if ($selisihKePulang < $minSelisihPulang && $selisihKePulang < 480) {
                        $minSelisihPulang = $selisihKePulang;
                        $jamPulangLog    = $entry['time'];
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
            ->body("Berhasil memproses & menyatukan $totalProcessed data absensi pegawai.")
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
