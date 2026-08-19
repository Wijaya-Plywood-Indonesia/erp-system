<?php
// app/Services/Target/TargetPotonganService.php
namespace App\Services\Target;

use App\DataTransferObjects\TargetHitungInput;
use App\DataTransferObjects\TargetHitungResult;
use App\Enums\StrategiPembagian;

class TargetPotonganService
{
    /**
     * Hitung target adjusted & potongan.
     *
     * Rate per-orang-per-menit dihitung dari master Target (targetNormal /
     * jamNormal / orgNormal). Cara MEMBAGI potongan ke tiap pekerja
     * ditentukan oleh $strategi (Kolektif / IndividualTarget) — bukan lagi
     * hardcoded di sini, supaya gampang nambah cara pembagian baru tanpa
     * ubah rumus rate.
     */
    public function hitung(TargetHitungInput $in, StrategiPembagian $strategi): TargetHitungResult
    {
        $menitNormalTotal = $in->jamNormal * 60;

        $ratePerMenit = ($in->orgNormal > 0 && $menitNormalTotal > 0)
            ? $in->targetNormal / $menitNormalTotal
            : 0;

        $ratePerOrgPerMenit = $in->orgNormal > 0 ? $ratePerMenit / $in->orgNormal : 0;

        $strategy = StrategiPembagianFactory::make($strategi);

        $hasil = $strategy->bagikan(
            pekerja: $in->pekerja,
            ratePerOrgPerMenit: $ratePerOrgPerMenit,
            biayaPerUnit: $in->biayaPerUnit,
            hasilAktual: $in->hasilAktual,
            gaji: $in->gaji,
        );

        $targetAdjusted = $hasil['targetAdjusted'];
        $kekurangan     = max(0, $targetAdjusted - $in->hasilAktual);
        $potonganTotal  = array_sum($hasil['potonganPerPegawai']);

        return new TargetHitungResult(
            targetAdjusted: $targetAdjusted,
            kekurangan: $kekurangan,
            potongan: $potonganTotal,
            potonganPerPegawai: $hasil['potonganPerPegawai'],
        );
    }
}