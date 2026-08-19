<?php
// app/DataTransferObjects/PekerjaKerjaInput.php
namespace App\DataTransferObjects;

final class PekerjaKerjaInput
{
    public function __construct(
        public readonly string $idPegawai,
        public readonly float $menitKerja,
        public readonly float $hasilIndividu = 0,
    ) {}
}
