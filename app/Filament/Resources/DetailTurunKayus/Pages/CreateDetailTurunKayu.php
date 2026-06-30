<?php

namespace App\Filament\Resources\DetailTurunKayus\Pages;

use App\Filament\Resources\DetailTurunKayus\DetailTurunKayuResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use App\Services\WatermarkService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateDetailTurunKayu extends CreateRecord
{
    protected static string $resource = DetailTurunKayuResource::class;

    protected array $pegawaiIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pegawaiIds = $data['id_pegawai'] ?? [];

        if (!is_array($this->pegawaiIds)) {
            $this->pegawaiIds = [$this->pegawaiIds];
        }

        if (empty($this->pegawaiIds)) {
            Notification::make()
                ->title('Error')
                ->body('Pilih minimal 1 pegawai!')
                ->danger()
                ->send();

            $this->halt();
        }

        $data['id_pegawai'] = $this->pegawaiIds[0];

        return $data;
    }

    protected function afterCreate(): void
    {
        $firstRecord = $this->record;
        $firstRecord->refresh();

        $originalFotoPath = $firstRecord->foto;

        Log::info("=== AFTER CREATE ===");
        Log::info("Foto path dari database: " . $originalFotoPath);

        if (!$originalFotoPath) {
            Log::warning("Tidak ada foto");
            $this->createOtherRecordsWithoutPhoto($firstRecord);
            return;
        }

        $originalFullPath = storage_path('app/public/' . $originalFotoPath);

        Log::info("Full path: " . $originalFullPath);
        Log::info("File exists: " . (file_exists($originalFullPath) ? 'YES' : 'NO'));

        if (!file_exists($originalFullPath)) {
            Log::error("File foto tidak ditemukan: {$originalFullPath}");
            Notification::make()
                ->title('Peringatan')
                ->body('File foto tidak ditemukan. Data tersimpan tanpa watermark.')
                ->warning()
                ->send();
            $this->createOtherRecordsWithoutPhoto($firstRecord);
            return;
        }

        $createdCount = 0;
        $records = [$firstRecord];

        // Buat record untuk pegawai lainnya
        if (count($this->pegawaiIds) > 1) {
            for ($i = 1; $i < count($this->pegawaiIds); $i++) {
                $pegawaiId = $this->pegawaiIds[$i];

                $newRecord = static::getModel()::create([
                    'id_turun_kayu' => $firstRecord->id_turun_kayu,
                    'id_kayu_masuk' => $firstRecord->id_kayu_masuk,
                    'id_pegawai' => $pegawaiId,
                    'status' => $firstRecord->status,
                    'foto' => null,
                ]);

                $records[] = $newRecord;
            }
        }

        // Proses setiap record
        foreach ($records as $index => $record) {
            try {
                $pegawai = $record->pegawai;
                $nama_supir = $record->nama_supir;

                if (!$pegawai) {
                    Log::warning("Pegawai tidak ditemukan untuk record ID: {$record->id}");
                    continue;
                }

                // Generate nama file unik
                $extension = pathinfo($originalFotoPath, PATHINFO_EXTENSION);
                $timestamp = now()->format('Ymd_His');
                $randomString = substr(md5($record->id . $pegawai->kode_pegawai . time()), 0, 8);
                $newFileName = "turun-kayu-bukti/watermark_{$pegawai->kode_pegawai}_{$timestamp}_{$randomString}.{$extension}";
                $newFullPath = storage_path('app/public/' . $newFileName);

                // Pastikan direktori ada
                $directory = dirname($newFullPath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                // Copy file asli
                if (!copy($originalFullPath, $newFullPath)) {
                    throw new \Exception("Gagal copy file ke: {$newFullPath}");
                }

                Log::info("File dicopy: {$newFullPath}");

                // COMPRESS DULU (ukuran < 1MB)
                WatermarkService::compressAndResize($newFileName, [
                    'max_width' => 1200,
                    'quality' => 80,
                    'strip' => true,
                ]);

                Log::info("File berhasil di-compress");

                // BARU TAMBAHKAN WATERMARK
                WatermarkService::addWatermarkWithGradient($newFileName, [
                    'nama_supir' => $nama_supir,
                    'position' => 'bottom-right',
                    'margin' => 30,
                    'font_size' => 32,
                    'bg_opacity' => 0.75,
                    'date_format' => 'd F Y',
                ]);

                Log::info("Watermark berhasil ditambahkan");

                // Update record
                $record->update(['foto' => $newFileName]);
                $createdCount++;

                Log::info("Record updated: {$newFileName}");

            } catch (\Exception $e) {
                Log::error("Gagal proses untuk record ID {$record->id}: " . $e->getMessage());
                Log::error("Stack trace: " . $e->getTraceAsString());

                if ($index === 0) {
                    $record->update(['foto' => $originalFotoPath]);
                } else {
                    $record->update(['foto' => null]);
                }
            }
        }

        // Hapus file asli
        if (file_exists($originalFullPath) && $createdCount > 0) {
            try {
                unlink($originalFullPath);
                Log::info("File asli dihapus: {$originalFullPath}");
            } catch (\Exception $e) {
                Log::warning("Gagal hapus file asli: " . $e->getMessage());
            }
        }

        Notification::make()
            ->title('Berhasil!')
            ->body("{$createdCount} data turun kayu dengan watermark & compress berhasil dibuat.")
            ->success()
            ->duration(5000)
            ->send();
    }

    protected function createOtherRecordsWithoutPhoto($firstRecord): void
    {
        $createdCount = 1;

        if (count($this->pegawaiIds) > 1) {
            for ($i = 1; $i < count($this->pegawaiIds); $i++) {
                $pegawaiId = $this->pegawaiIds[$i];

                static::getModel()::create([
                    'id_turun_kayu' => $firstRecord->id_turun_kayu,
                    'id_kayu_masuk' => $firstRecord->id_kayu_masuk,
                    'id_pegawai' => $pegawaiId,
                    'status' => $firstRecord->status,
                    'foto' => null,
                ]);

                $createdCount++;
            }
        }

        Notification::make()
            ->title('Data Tersimpan')
            ->body("{$createdCount} data tersimpan (tanpa foto bukti).")
            ->warning()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}