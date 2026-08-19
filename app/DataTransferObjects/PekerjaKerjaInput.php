<?php
// app/DataTransferObjects/PekerjaKerjaInput.php
namespace App\DataTransferObjects;

final class PekerjaKerjaInput
{
    public function __construct(
        public readonly string $idPegawai,
        public readonly float $menitKerja,   // durasi kerja aktual pegawai ini, dalam menit
    ) {}
}
