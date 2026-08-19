<?php
// app/DataTransferObjects/TargetHitungInput.php
namespace App\DataTransferObjects;

final class TargetHitungInput
{
    /** @param PekerjaKerjaInput[] $pekerja */
    public function __construct(
        public readonly float $targetNormal,   // target normal (master)
        public readonly int $orgNormal,        // orang normal (master)
        public readonly float $jamNormal,      // jam normal (master)
        public readonly array $pekerja,        // durasi kerja aktual TIAP pegawai (menggantikan orgAktual+jamAktual+menitAktual)
        public readonly float $hasilAktual,    // hasil produksi aktual
        public readonly float $biayaPerUnit,   // potongan (generated column dari master Target)
        public readonly float $gaji,
    ) {}
}
