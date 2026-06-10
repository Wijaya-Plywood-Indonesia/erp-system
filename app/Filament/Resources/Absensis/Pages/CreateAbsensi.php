<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Models\DetailAbsensi;
use App\Services\AbsensiParsingService;
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
        $nextDate = Carbon::parse($record->tanggal)->addDay()->format('Y-m-d');

        $files = $record->file_path;
        if (empty($files) || !is_array($files)) {
            return;
        }

        /** @var AbsensiParsingService $parsingService */
        $parsingService = app(AbsensiParsingService::class);
        $parseResult = $parsingService->collectLogs($files, $targetDate, $nextDate);

        $rawLogs = $parseResult['raw_logs'];
        $skippedLines = $parseResult['skipped_lines'];
        $totalProcessed = 0;

        // ================================================
        // STEP 2: PROSES — Tentukan masuk & pulang per orang
        // ================================================
        foreach ($rawLogs as $empCode => $entries) {
            // Urutkan semua tap dari yang paling awal
            $sorted = collect($entries)->sortBy('full');

            // Tap pertama di hari target = jam masuk
            $firstTap = $sorted->filter(fn($l) => $l['date'] === $targetDate)->first();
            if (!$firstTap) continue; // Tidak ada tap di hari target, skip

            $jamMasuk   = $firstTap['time'];
            $jamMasukDt = Carbon::parse($firstTap['full']);

            // ⭐ DETEKSI SHIFT DARI JAM MASUK
            // Shift Malam: tap masuk antara jam 14:00 - 23:59
            $isShiftMalam = $jamMasukDt->hour >= 14;

            if ($isShiftMalam) {
                // Shift Malam: cari tap pulang di nextDate (jam 00:00 - 10:00)
                $lastTap = $sorted
                    ->filter(fn($l) => $l['date'] === $nextDate
                        && Carbon::parse($l['full'])->hour <= 10)
                    ->last();
            } else {
                // Shift Pagi: cari tap pulang di hari yang sama, setelah jam masuk
                $lastTap = $sorted
                    ->filter(fn($l) => $l['date'] === $targetDate
                        && Carbon::parse($l['full'])->gt($jamMasukDt))
                    ->last();
            }

            $jamPulang = $lastTap['time'] ?? null;

            // ================================================
            // STEP 3: SIMPAN ke database
            // ================================================
            DetailAbsensi::updateOrCreate(
                ['kode_pegawai' => $empCode, 'tanggal' => $targetDate],
                [
                    'id_absensi' => $record->id,
                    'jam_masuk'  => $jamMasuk,
                    'jam_pulang' => $jamPulang,
                ]
            );

            $totalProcessed++;
        }

        Notification::make()
            ->success()
            ->title('Import Berhasil')
            ->body("Berhasil memproses $totalProcessed data pegawai. {$skippedLines} baris dilewati karena format tidak valid.")
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
