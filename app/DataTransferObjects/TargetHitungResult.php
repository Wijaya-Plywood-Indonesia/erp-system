<?php
// app/DataTransferObjects/TargetHitungResult.php
namespace App\DataTransferObjects;

final class TargetHitungResult
{
    /**
     * @param array<string, float> $potonganPerPegawai  idPegawai => nilai potongan (Rp)
     */
    public function __construct(
        public readonly float $targetAdjusted,
        public readonly float $kekurangan,
        public readonly float $potongan,             // total potongan (sum semua pekerja)
        public readonly array $potonganPerPegawai,    // per-orang, sudah dibulatkan ke masing2 strategi
    ) {}
}