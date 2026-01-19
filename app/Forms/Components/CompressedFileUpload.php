<?php

namespace App\Forms\Components;

use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Closure;

class CompressedFileUpload extends FileUpload
{
    // Properti untuk menyimpan logika penamaan
    protected Closure | string | null $fileNameGenerator = null;

    // Method agar kita bisa set nama dari Form
    public function fileName(Closure | string $name): static
    {
        $this->fileNameGenerator = $name;
        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->image();
        $this->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->imageResizeMode('contain');
        $this->imageResizeTargetWidth('1024');
        $this->imageResizeTargetHeight('1024');

        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file) {

            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getRealPath());

            $image->scaleDown(width: 1024, height: 1024);
            $encoded = $image->toWebp(quality: 80);

            $folder = $this->getDirectory() ?? 'uploads';
            $disk = $this->getDiskName() ?? 'public';

            // ==========================================
            // LOGIKA PENAMAAN BARU DISINI
            // ==========================================

            // 1. Cek apakah ada generator nama custom?
            if ($this->fileNameGenerator) {
                // Eksekusi fungsi penamaan & inject dependency ($get) secara otomatis
                $customName = $this->evaluate($this->fileNameGenerator);
                // Bersihkan nama agar aman untuk file (ganti spasi jadi -, hapus karakter aneh)
                $nameBase = Str::slug($customName);
            } else {
                // Default pakai UUID jika tidak ada custom name
                $nameBase = Str::uuid()->toString();
            }

            // Tambahkan akhiran .webp
            $filename = $nameBase . '.webp';
            $finalPath = $folder . '/' . $filename;

            // Cek jika file dengan nama sama sudah ada, tambahkan angka random biar gak bentrok
            if (Storage::disk($disk)->exists($finalPath)) {
                $filename = $nameBase . '-' . Str::random(4) . '.webp';
                $finalPath = $folder . '/' . $filename;
            }

            Storage::disk($disk)->put($finalPath, (string) $encoded);

            return $finalPath;
        });
    }
}
