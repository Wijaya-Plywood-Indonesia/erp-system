<?php

namespace App\Services\FingerParsers;

use RuntimeException;

class FingerParserManager
{
    /** @var FingerParserInterface[] */
    protected array $parsers;

    public function __construct(array $parsers)
    {
        $this->parsers = $parsers;
    }

    /**
     * Deteksi parser yang cocok berdasarkan baris pertama file.
     * Tambah format baru? Cukup daftarkan parser baru di config/service
     * provider, tidak perlu ubah kode ini.
     */
    public function resolve(string $absolutePath): FingerParserInterface
    {
        $handle = fopen($absolutePath, 'r');
        $firstLine = $handle ? (fgets($handle) ?: '') : '';

        if ($handle) {
            fclose($handle);
        }

        foreach ($this->parsers as $parser) {
            if ($parser->supports($firstLine)) {
                return $parser;
            }
        }

        throw new RuntimeException(
            "Format file tidak dikenali: {$absolutePath}. Pastikan file sesuai salah satu format mesin yang didukung."
        );
    }
}
