<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Models\DetailAbsensi;
use App\Services\AbsensiParsingService;
use App\Services\AbsensiPairingService;
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

        /** @var AbsensiPairingService $pairingService */
        $pairingService = app(AbsensiPairingService::class);

        // ================================================
        // STEP 2: PROSES — Tentukan masuk & pulang per orang
        // ================================================
        foreach ($rawLogs as $empCode => $entries) {
            // Find previous day's attendance
            $prevDate = Carbon::parse($targetDate)->subDay()->format('Y-m-d');
            $previousAbsen = DetailAbsensi::where('kode_pegawai', $empCode)
                ->where('tanggal', $prevDate)
                ->first();

            $prevCheckout = null;
            if ($previousAbsen && $previousAbsen->jam_pulang) {
                $prevCheckout = Carbon::parse($prevDate . ' ' . $previousAbsen->jam_pulang);
            }

            $paired = $pairingService->pairEmployeeLogs($entries, $targetDate, $nextDate, $prevCheckout);
            if (!$paired) {
                continue; // Tidak ada tap masuk di hari target, skip
            }

            $jamMasuk = $paired['jam_masuk'];
            $jamPulang = $paired['jam_pulang'];

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
            ->body("Berhasil memproses $totalProcessed data pegawai, {$skippedLines} baris dilewati karena format tidak valid.")
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
