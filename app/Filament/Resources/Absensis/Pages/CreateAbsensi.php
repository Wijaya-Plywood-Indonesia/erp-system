<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Models\DetailAbsensi;
use App\Models\DetailPegawai;
use App\Models\PegawaiSanding;
use App\Models\Pegawai;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class CreateAbsensi extends CreateRecord
{
    protected static string $resource = AbsensiResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        $targetDate = Carbon::parse($record->tanggal)->format('Y-m-d');
        $nextDate = Carbon::parse($record->tanggal)->addDay()->format('Y-m-d');

        $files = $record->file_path;
        if (empty($files) || !is_array($files)) return;

        $rawLogs = [];
        $totalProcessed = 0;

        // 1. Parsing SEMUA data finger (Tanpa kecuali)
        foreach ($files as $file) {
            if (!Storage::disk('public')->exists($file)) continue;
            $fileContent = Storage::disk('public')->get($file);
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $fileContent));

            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if (empty($trimmedLine)) continue;
                $parts = preg_split('/\s+/', $trimmedLine);

                foreach ($parts as $index => $value) {
                    if (preg_match('/^\d{2,4}[\/\-]\d{1,2}[\/\-]\d{2,4}$/', $value)) {
                        try {
                            $dateInFile = Carbon::parse(str_replace('/', '-', $value))->format('Y-m-d');
                            $timeInFile = $parts[$index + 1] ?? null;
                            $empCode = ltrim($parts[2] ?? '', '0');

                            if ($empCode && $timeInFile && ($dateInFile === $targetDate || $dateInFile === $nextDate)) {
                                $rawLogs[$empCode][] = [
                                    'date' => $dateInFile,
                                    'time' => $timeInFile,
                                    'full' => Carbon::parse("$dateInFile $timeInFile")
                                ];
                            }
                        } catch (\Exception $e) {
                            continue;
                        }
                        break;
                    }
                }
            }
        }

        // 2. Olah Data: Gunakan Log Finger sebagai Driver, Produksi sebagai Filter Shift
        foreach ($rawLogs as $empCode => $logEntries) {
            $sortedLogs = collect($logEntries)->sortBy('full');

            // Pastikan ada tap di hari target
            $firstLogToday = $sortedLogs->filter(fn($l) => $l['date'] === $targetDate)->first();
            if (!$firstLogToday) continue;

            // Ambil Data Pegawai untuk Relasi
            $pegawaiMaster = \App\Models\Pegawai::where('kode_pegawai', $empCode)->first();

            $shift = 'PAGI'; // Default
            $divisiName = 'UMUM';

            if ($pegawaiMaster) {
                // Cek Produksi DRYER (Kolom: tanggal_produksi)
                $detailDryer = \App\Models\DetailPegawai::where('id_pegawai', $pegawaiMaster->id)
                    ->whereHas('produksi', fn($q) => $q->where('tanggal_produksi', $targetDate))->first();

                if ($detailDryer) {
                    $divisiName = 'DRYER';
                    $shift = strtoupper($detailDryer->produksi->shift ?? 'PAGI');
                } else {
                    // Cek Produksi SANDING (Kolom: tanggal)
                    $detailSanding = \App\Models\PegawaiSanding::where('id_pegawai', $pegawaiMaster->id)
                        ->whereHas('produksiSanding', fn($q) => $q->where('tanggal', $targetDate))->first();

                    if ($detailSanding) {
                        $divisiName = 'SANDING';
                        $shift = strtoupper($detailSanding->produksiSanding->shift ?? 'PAGI');
                    }
                }
            }

            // --- LOGIKA SWITCH BERDASARKAN FILTER SHIFT ---
            if ($shift === 'MALAM') {
                // Jika SHIFT MALAM: Jam Masuk harus sore (>= 15:00), Jam Pulang besok pagi
                $inLog = $sortedLogs->filter(fn($l) => $l['date'] === $targetDate && Carbon::parse($l['time'])->hour >= 15)->first();
                $jamMasuk = $inLog ? $inLog['time'] : $firstLogToday['time'];

                $outLog = $sortedLogs->filter(fn($l) => $l['date'] === $nextDate && Carbon::parse($l['time'])->hour <= 10)->last();
                $jamPulang = $outLog ? $outLog['time'] : null;
            } else {
                // Jika SHIFT PAGI: Normal (Semua di hari yang sama)
                $jamMasuk = $firstLogToday['time'];
                $lastLogToday = $sortedLogs->filter(fn($l) => $l['date'] === $targetDate && Carbon::parse($l['time'])->gt(Carbon::parse($jamMasuk)))->last();
                $jamPulang = $lastLogToday['time'] ?? null;
            }

            // 3. Simpan ke Database
            \App\Models\DetailAbsensi::updateOrCreate(
                ['kode_pegawai' => $empCode, 'tanggal' => $targetDate],
                [
                    'id_absensi' => $record->id,
                    'jam_masuk'  => $jamMasuk,
                    'jam_pulang' => $jamPulang,
                    'keterangan' => "$divisiName $shift"
                ]
            );
            $totalProcessed++;
        }

        Notification::make()
            ->success()
            ->title('Sinkronisasi Berhasil')
            ->body("Memproses $totalProcessed data pegawai dengan filter shift produksi.")
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
