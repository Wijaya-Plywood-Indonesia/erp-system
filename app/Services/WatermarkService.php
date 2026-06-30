<?php

namespace App\Services;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use Illuminate\Support\Carbon;
use App\Services\Log;
use Exception;

class WatermarkService
{
    /**
     * Add text watermark to image
     *
     * @param string $sourcePath Path to source image
     * @param string $destinationPath Path to save watermarked image
     * @param string $watermarkText Text to use as watermark
     * @param array $options Watermark options
     * @return bool
     * @throws Exception
     */
    public function addTextWatermark(
        string $sourcePath,
        string $destinationPath,
        string $watermarkText,
        array $options = []
    ): bool {
        // Default options
        $defaults = [
            'position' => 'bottom-right', // top-left, top-right, bottom-left, bottom-right, center
            'font_size' => 40,
            'font_color' => '#FFFFFF',
            'opacity' => 0.5,
            'angle' => 0,
            'margin' => 20,
            'font_family' => null, // Path to TTF font file
            'stroke_color' => '#000000',
            'stroke_width' => 1,
        ];

        $options = array_merge($defaults, $options);

        try {
            // Check if source file exists
            if (!file_exists($sourcePath)) {
                throw new Exception("Source image not found: {$sourcePath}");
            }

            // Create Imagick object
            $image = new Imagick($sourcePath);

            // Get image dimensions
            $imageWidth = $image->getImageWidth();
            $imageHeight = $image->getImageHeight();

            // Create drawing object
            $draw = new ImagickDraw();

            // Set font properties
            if ($options['font_family'] && file_exists($options['font_family'])) {
                $draw->setFont($options['font_family']);
            }
            $draw->setFontSize($options['font_size']);
            $draw->setFillColor(new ImagickPixel($options['font_color']));

            // Add stroke/outline
            if ($options['stroke_width'] > 0) {
                $draw->setStrokeColor(new ImagickPixel($options['stroke_color']));
                $draw->setStrokeWidth($options['stroke_width']);
            }

            // Get text dimensions
            $metrics = $image->queryFontMetrics($draw, $watermarkText);
            $textWidth = $metrics['textWidth'];
            $textHeight = $metrics['textHeight'];

            // Calculate position
            $coordinates = $this->calculatePosition(
                $imageWidth,
                $imageHeight,
                $textWidth,
                $textHeight,
                $options['position'],
                $options['margin']
            );

            // Set text opacity
            $draw->setFillOpacity($options['opacity']);
            if ($options['stroke_width'] > 0) {
                $draw->setStrokeOpacity($options['opacity']);
            }

            // Annotate image
            $image->annotateImage(
                $draw,
                $coordinates['x'],
                $coordinates['y'],
                $options['angle'],
                $watermarkText
            );

            // Save image
            $image->writeImage($destinationPath);

            // Clean up
            $image->clear();
            $image->destroy();

            return true;

        } catch (Exception $e) {
            throw new Exception("Failed to add text watermark: " . $e->getMessage());
        }
    }

    /**
     * Add image watermark to image
     *
     * @param string $sourcePath Path to source image
     * @param string $destinationPath Path to save watermarked image
     * @param string $watermarkImagePath Path to watermark image
     * @param array $options Watermark options
     * @return bool
     * @throws Exception
     */
    public function addImageWatermark(
        string $sourcePath,
        string $destinationPath,
        string $watermarkImagePath,
        array $options = []
    ): bool {
        // Default options
        $defaults = [
            'position' => 'bottom-right',
            'opacity' => 0.5,
            'scale' => 0.2, // Watermark size relative to image (0.2 = 20%)
            'margin' => 20,
        ];

        $options = array_merge($defaults, $options);

        try {
            // Check if files exist
            if (!file_exists($sourcePath)) {
                throw new Exception("Source image not found: {$sourcePath}");
            }
            if (!file_exists($watermarkImagePath)) {
                throw new Exception("Watermark image not found: {$watermarkImagePath}");
            }

            // Load images
            $image = new Imagick($sourcePath);
            $watermark = new Imagick($watermarkImagePath);

            // Get dimensions
            $imageWidth = $image->getImageWidth();
            $imageHeight = $image->getImageHeight();

            // Scale watermark
            $watermarkWidth = $watermark->getImageWidth();
            $watermarkHeight = $watermark->getImageHeight();

            // Calculate new watermark size
            $maxSize = min($imageWidth, $imageHeight) * $options['scale'];
            $ratio = min($maxSize / $watermarkWidth, $maxSize / $watermarkHeight);

            $newWidth = (int) ($watermarkWidth * $ratio);
            $newHeight = (int) ($watermarkHeight * $ratio);

            $watermark->scaleImage($newWidth, $newHeight);

            // Set opacity
            $watermark->evaluateImage(Imagick::EVALUATE_MULTIPLY, $options['opacity'], Imagick::CHANNEL_ALPHA);

            // Calculate position
            $coordinates = $this->calculatePosition(
                $imageWidth,
                $imageHeight,
                $newWidth,
                $newHeight,
                $options['position'],
                $options['margin']
            );

            // Composite watermark onto image
            $image->compositeImage(
                $watermark,
                Imagick::COMPOSITE_OVER,
                $coordinates['x'],
                $coordinates['y']
            );

            // Save image
            $image->writeImage($destinationPath);

            // Clean up
            $watermark->clear();
            $watermark->destroy();
            $image->clear();
            $image->destroy();

            return true;

        } catch (Exception $e) {
            throw new Exception("Failed to add image watermark: " . $e->getMessage());
        }
    }

    /**
     * Add tiled text watermark (repeated across image)
     *
     * @param string $sourcePath Path to source image
     * @param string $destinationPath Path to save watermarked image
     * @param string $watermarkText Text to use as watermark
     * @param array $options Watermark options
     * @return bool
     * @throws Exception
     */
    public function addTiledTextWatermark(
        string $sourcePath,
        string $destinationPath,
        string $watermarkText,
        array $options = []
    ): bool {
        // Default options
        $defaults = [
            'font_size' => 32,
            'font_color' => '#FFFFFF',
            'opacity' => 0.15,
            'angle' => -45,
            'spacing_x' => 150,
            'spacing_y' => 150,
            'font_family' => null,
        ];

        $options = array_merge($defaults, $options);

        try {
            if (!file_exists($sourcePath)) {
                throw new Exception("Source image not found: {$sourcePath}");
            }

            $image = new Imagick($sourcePath);
            $imageWidth = $image->getImageWidth();
            $imageHeight = $image->getImageHeight();

            $draw = new ImagickDraw();

            if ($options['font_family'] && file_exists($options['font_family'])) {
                $draw->setFont($options['font_family']);
            }
            $draw->setFontSize($options['font_size']);
            $draw->setFillColor(new ImagickPixel($options['font_color']));
            $draw->setFillOpacity($options['opacity']);

            // Get text dimensions
            $metrics = $image->queryFontMetrics($draw, $watermarkText);
            $textWidth = $metrics['textWidth'];

            // Calculate diagonal distance for rotation
            $diagonal = sqrt(pow($imageWidth, 2) + pow($imageHeight, 2));

            // Draw watermark in a grid pattern
            for ($y = -$diagonal; $y < $imageHeight + $diagonal; $y += $options['spacing_y']) {
                for ($x = -$diagonal; $x < $imageWidth + $diagonal; $x += $options['spacing_x']) {
                    $image->annotateImage(
                        $draw,
                        $x,
                        $y,
                        $options['angle'],
                        $watermarkText
                    );
                }
            }

            $image->writeImage($destinationPath);

            $image->clear();
            $image->destroy();

            return true;

        } catch (Exception $e) {
            throw new Exception("Failed to add tiled watermark: " . $e->getMessage());
        }
    }

    /**
     * Batch process multiple images with watermark
     *
     * @param array $sourcePaths Array of source image paths
     * @param string $outputDirectory Directory to save watermarked images
     * @param string $watermark Watermark text or image path
     * @param string $type 'text' or 'image'
     * @param array $options Watermark options
     * @return array Results with success/failure for each image
     */
    public function batchWatermark(
        array $sourcePaths,
        string $outputDirectory,
        string $watermark,
        string $type = 'text',
        array $options = []
    ): array {
        $results = [];

        // Create output directory if it doesn't exist
        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }

        foreach ($sourcePaths as $sourcePath) {
            $filename = basename($sourcePath);
            $destinationPath = rtrim($outputDirectory, '/') . '/' . $filename;

            try {
                if ($type === 'text') {
                    $success = $this->addTextWatermark($sourcePath, $destinationPath, $watermark, $options);
                } else {
                    $success = $this->addImageWatermark($sourcePath, $destinationPath, $watermark, $options);
                }

                $results[] = [
                    'source' => $sourcePath,
                    'destination' => $destinationPath,
                    'success' => $success,
                    'error' => null
                ];

            } catch (Exception $e) {
                $results[] = [
                    'source' => $sourcePath,
                    'destination' => $destinationPath,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Calculate watermark position based on option
     *
     * @param int $imageWidth
     * @param int $imageHeight
     * @param int $watermarkWidth
     * @param int $watermarkHeight
     * @param string $position
     * @param int $margin
     * @return array
     */
    private function calculatePosition(
        int $imageWidth,
        int $imageHeight,
        int $watermarkWidth,
        int $watermarkHeight,
        string $position,
        int $margin
    ): array {
        $x = 0;
        $y = 0;

        switch ($position) {
            case 'top-left':
                $x = $margin;
                $y = $margin + $watermarkHeight;
                break;

            case 'top-right':
                $x = $imageWidth - $watermarkWidth - $margin;
                $y = $margin + $watermarkHeight;
                break;

            case 'bottom-left':
                $x = $margin;
                $y = $imageHeight - $margin;
                break;

            case 'bottom-right':
                $x = $imageWidth - $watermarkWidth - $margin;
                $y = $imageHeight - $margin;
                break;

            case 'center':
                $x = ($imageWidth - $watermarkWidth) / 2;
                $y = ($imageHeight + $watermarkHeight) / 2;
                break;

            case 'top-center':
                $x = ($imageWidth - $watermarkWidth) / 2;
                $y = $margin + $watermarkHeight;
                break;

            case 'bottom-center':
                $x = ($imageWidth - $watermarkWidth) / 2;
                $y = $imageHeight - $margin;
                break;

            default:
                $x = $imageWidth - $watermarkWidth - $margin;
                $y = $imageHeight - $margin;
        }

        return ['x' => (int) $x, 'y' => (int) $y];
    }

    /**
     * Check if Imagick extension is loaded
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('imagick');
    }

    /**
     * Get Imagick version info
     *
     * @return array|null
     */
    public static function getVersion(): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        return Imagick::getVersion();
    }

    public static function addWatermarkWithGradient(
        string $filePath,
        array $options = []
    ): bool {
        if (!self::isAvailable()) {
            throw new Exception('Imagick extension not loaded.');
        }

        $fullPath = storage_path('app/public/' . $filePath);
        if (!file_exists($fullPath)) {
            throw new Exception("File not found: {$fullPath}");
        }

        $defaults = [
            'nama_supir' => 'Unknown',
            'position' => 'bottom-right',
            'margin' => 25,
            'font_size' => 40,
            'font_color' => '#FFFFFF',
            'stroke_color' => '#000000',
            'stroke_width' => 2,
            'opacity' => 0.8,
            'line_spacing' => 12,
            'date_format' => 'd F Y', // 14 November 2025
            'bg_height' => 90,        // tinggi background
            'bg_opacity' => 0.7,      // 70% hitam
        ];

        $options = array_merge($defaults, $options);

        try {
            $image = new Imagick($fullPath);
            $draw = new ImagickDraw();

            // === FONT ===
            $draw->setFontSize($options['font_size']);
            $draw->setFillColor(new ImagickPixel($options['font_color']));
            $draw->setFillOpacity($options['opacity']);
            $draw->setStrokeColor(new ImagickPixel($options['stroke_color']));
            $draw->setStrokeWidth($options['stroke_width']);
            $draw->setStrokeAntialias(true);

            // === TEKS ===
            $tanggal = Carbon::now()->translatedFormat($options['date_format']);
            $supir = strtoupper($options['nama_supir']);

            $line1 = "TANGGAL: {$tanggal}";
            $line2 = "SUPIR: {$supir}";

            $metrics1 = $image->queryFontMetrics($draw, $line1);
            $metrics2 = $image->queryFontMetrics($draw, $line2);

            $textWidth = max($metrics1['textWidth'], $metrics2['textWidth']);
            $textHeight = $metrics1['textHeight'] + $metrics2['textHeight'] + $options['line_spacing'];
            $totalHeight = max($options['bg_height'], $textHeight + 20);

            $imageWidth = $image->getImageWidth();
            $imageHeight = $image->getImageHeight();

            // === POSISI ===
            $x = $options['margin'];
            $y = $imageHeight - $options['margin'];

            switch ($options['position']) {
                case 'top-left':
                    $x = $options['margin'];
                    $y = $options['margin'] + $totalHeight;
                    break;
                case 'top-right':
                    $x = $imageWidth - $textWidth - $options['margin'] * 2;
                    $y = $options['margin'] + $totalHeight;
                    break;
                case 'bottom-left':
                    $x = $options['margin'];
                    $y = $imageHeight - $options['margin'];
                    break;
                case 'center':
                    $x = ($imageWidth - $textWidth) / 2;
                    $y = ($imageHeight + $totalHeight) / 2;
                    break;
                default: // bottom-right
                    $x = $imageWidth - $textWidth - $options['margin'] * 2;
                    $y = $imageHeight - $options['margin'];
            }

            // === BUAT BACKGROUND GRADIASI HITAM ===
            $bg = new Imagick();
            $bg->newImage($imageWidth, $totalHeight, 'none');

            // Gradiasi hitam (atas gelap → bawah transparan)
            $gradient = new Imagick();
            $gradient->newPseudoImage($textWidth + 60, $totalHeight, "gradient:black-transparent");

            // Mask: hanya di area teks
            $mask = new Imagick();
            $mask->newImage($textWidth + 60, $totalHeight, 'black');
            $mask->compositeImage($gradient, Imagick::COMPOSITE_OVER, 0, 0);

            // Tempel ke background
            $bgX = $x - 30;
            $bgY = $y - $totalHeight + 10;
            $bg->compositeImage($mask, Imagick::COMPOSITE_OVER, $bgX, $bgY);

            // Opacity
            $bg->evaluateImage(Imagick::EVALUATE_MULTIPLY, $options['bg_opacity'], \Imagick::CHANNEL_ALPHA);

            // Gabung ke gambar utama
            $image->compositeImage($bg, Imagick::COMPOSITE_OVER, 0, $imageHeight - $totalHeight);

            // === GAMBAR TEKS ===
            $textY1 = $y - ($totalHeight - $metrics1['textHeight']) / 2 - 10;
            $textY2 = $textY1 + $metrics1['textHeight'] + $options['line_spacing'];

            $image->annotateImage($draw, $x, $textY1, 0, $line1);
            $image->annotateImage($draw, $x, $textY2, 0, $line2);

            // Simpan
            $image->writeImage($fullPath);

            $image->clear();
            $image->destroy();
            $bg->clear();
            $bg->destroy();

            return true;

        } catch (Exception $e) {
            Log::error("Watermark gagal: " . $e->getMessage());
            return false;
        }
    }

    public static function compressAndResize(string $filePath, array $options = []): bool
    {
        if (!self::isAvailable()) {
            throw new Exception('Imagick extension not loaded.');
        }

        $fullPath = storage_path('app/public/' . $filePath);
        if (!file_exists($fullPath)) {
            throw new Exception("File not found: {$fullPath}");
        }

        $defaults = [
            'max_width' => 1200,
            'quality' => 75,
            'strip' => true,
        ];
        $options = array_merge($defaults, $options);

        try {
            $image = new Imagick($fullPath);

            // === RESIZE ===
            $image->setImageCompression(Imagick::COMPRESSION_JPEG);
            $image->setImageCompressionQuality($options['quality']);

            if ($options['strip']) {
                $image->stripImage(); // Hapus EXIF
            }

            // Resize jika lebar > max_width
            $width = $image->getImageWidth();
            if ($width > $options['max_width']) {
                $image->resizeImage($options['max_width'], 0, Imagick::FILTER_LANCZOS, 1);
            }

            // === OPTIMASI UKURAN (loop sampai < 1MB) ===
            $image->writeImage($fullPath);

            // Cek ukuran
            $size = filesize($fullPath);
            if ($size > 1024 * 1024) { // > 1MB
                $quality = $options['quality'];
                while ($size > 1024 * 1024 && $quality > 30) {
                    $quality -= 5;
                    $image->setImageCompressionQuality($quality);
                    $image->writeImage($fullPath);
                    clearstatcache();
                    $size = filesize($fullPath);
                }
            }

            $image->clear();
            $image->destroy();

            return true;

        } catch (Exception $e) {
            Log::error("Compress gagal: " . $e->getMessage());
            return false;
        }
    }
}