<?php
// app/DataTransferObjects/PekerjaKerjaInput.php
namespace App\DataTransferObjects;

final class PekerjaKerjaInput
{
    public function __construct(
        public readonly string $idPegawai,
        public readonly float $menitKerja,   // durasi kerja individual
        public readonly float $hasilIndividu = 0, // hasil khusus orang ini, kalau bisa dipisah (mis. per meja)
    ) {}
}
