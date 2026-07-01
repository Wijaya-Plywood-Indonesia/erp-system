<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Models\DetailAbsensi;
use App\Models\Pegawai;
use App\Models\ProduksiGrajitriplek;
use App\Models\ProduksiHp;
use App\Models\ProduksiPressDryer;
use App\Models\ProduksiSanding;
use App\Services\AbsensiPairingService;
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

                    $carbonLog = Carbon::parse(str_replace('/', '-', $dateTimeString), 'Asia/Jakarta');
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

        // ===============================================

        $semuaPegawai = Pegawai::all()
            ->keyBy(fn($p) => ltrim($p->kode_pegawai, '0'));

        // Pre-load shift produksi untuk targetDate sekaligus
        $shiftDryer   = ProduksiPressDryer::whereDate('tanggal_produksi', $targetDate)
            ->with('detailPegawais')
            ->get();
        $shiftHp      = ProduksiHp::whereDate('tanggal_produksi', $targetDate)
            ->with('detailPegawaiHp')
            ->get();
        $shiftSanding = ProduksiSanding::whereDate('tanggal', $targetDate)
            ->with('pegawaiSandings')
            ->get();
        $shiftGraji   = ProduksiGrajitriplek::whereDate('tanggal_produksi', $targetDate)
            ->with('pegawaiGrajiTriplek')
            ->get();

        $pairingService = app(AbsensiPairingService::class);

        // ================================================
        // STEP 2 + 3: PAIRING & SIMPAN (digabung, pakai Service)
        // ================================================
        foreach ($rawLogs as $empCode => $entries) {
            $pegawai     = $semuaPegawai->get($empCode);
            $forcedShift = null;

            if ($pegawai) {
                // Cek shift dari data produksi yang sudah di-preload
                $shifts = array_filter([
                    $shiftDryer->first(
                        fn($r) => $r->detailPegawais->contains(fn($d) => $d->id_pegawai === $pegawai->id)
                    )?->shift,
                    $shiftHp->first(
                        fn($r) => $r->detailPegawaiHp->contains(fn($d) => $d->id_pegawai === $pegawai->id)
                    )?->shift,
                    $shiftSanding->first(
                        fn($r) => $r->pegawaiSandings->contains(fn($d) => $d->id_pegawai === $pegawai->id)
                    )?->shift,
                    $shiftGraji->first(
                        fn($r) => $r->pegawaiGrajiTriplek->contains(fn($d) => $d->id_pegawai === $pegawai->id)
                    )?->shift,
                ]);

                if (count($shifts) > 0) {
                    $forcedShift = strtoupper(trim(reset($shifts)));

                    if (count(array_unique($shifts)) > 1) {
                        Log::warning("Conflicting shift for $empCode on $targetDate", compact('shifts'));
                    }
                }

                // Fallback ke jadwal master pegawai jika tidak ada di tabel produksi
                if (!$forcedShift) {
                    $jamMasukSistem = $pegawai->jam_masuk_sistem ?? '07:00:00';
                    if (Carbon::parse($jamMasukSistem)->hour >= 14) {
                        $forcedShift = 'MALAM';
                    }
                }
            }

            // Pakai Service yang sudah matang logikanya
            $result = $pairingService->pairEmployeeLogs(
                entries: $entries,
                targetDate: $targetDate,
                nextDate: $nextDate,
                prevCheckout: null,
                forcedShift: $forcedShift,
            );

            if ($result) {
                DetailAbsensi::updateOrCreate(
                    ['kode_pegawai' => $empCode, 'tanggal' => $targetDate],
                    [
                        'id_absensi' => $record->id,
                        'jam_masuk'  => $result['jam_masuk'],
                        'jam_pulang' => $result['jam_pulang'],
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
