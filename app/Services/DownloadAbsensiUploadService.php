<?php

namespace App\Services;

use App\Models\NewAbsensiUpload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DownloadAbsensiUploadService
{
    protected const DISK = 'public';

    /**
     * @param  NewAbsensiUpload[]|Collection  $uploads  Boleh 1 batch (bisa berisi >1 file) atau beberapa batch sekaligus.
     */
    public function download($uploads): BinaryFileResponse|StreamedResponse
    {
        $uploads = collect($uploads);

        $allPaths = $uploads->flatMap(fn (NewAbsensiUpload $u) => $u->file_path)->values();

        if ($allPaths->isEmpty()) {
            abort(404, 'Tidak ada file untuk diunduh.');
        }

        // 1 file total -> download langsung, tidak perlu di-zip.
        if ($allPaths->count() === 1) {
            $path = $allPaths->first();

            return Storage::disk(self::DISK)->download($path, basename($path));
        }

        // Lebih dari 1 file -> zip dulu.
        $zipFileName = 'absensi-'.now()->format('Ymd-His').'.zip';
        $zipTempPath = storage_path('app/tmp/'.$zipFileName);

        if (! is_dir(dirname($zipTempPath))) {
            mkdir(dirname($zipTempPath), 0755, true);
        }

        $zip = new ZipArchive;
        $zip->open($zipTempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($allPaths as $path) {
            if (! Storage::disk(self::DISK)->exists($path)) {
                continue;
            }

            $localPath = Storage::disk(self::DISK)->path($path);
            $zip->addFile($localPath, basename($path));
        }

        $zip->close();

        return response()->download($zipTempPath, $zipFileName)->deleteFileAfterSend(true);
    }
}
