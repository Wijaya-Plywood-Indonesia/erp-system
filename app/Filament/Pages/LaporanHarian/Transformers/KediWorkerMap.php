<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use App\Actions\HitungPotonganProduksiAction;
use App\DataTransferObjects\PekerjaKerjaInput;
use App\Enums\Mesin;
use App\Enums\StrategiPembagian;
use App\Models\Target;
use App\Services\Target\TargetResolverFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class KediWorkerMap
{
    public static function make($collection): array
    {
        $results = [];
        $action = new HitungPotonganProduksiAction;

        foreach ($collection as $produksi) {

            // --- A. AMBIL DATA HASIL (SELALU DALAM SATUAN ASLINYA) ---
            $totalHasil = 0;
            $detailItems = collect();
            $labelDivisi = 'KEDI';

            if ($produksi->status === 'bongkar') {
                if ($produksi->detailBongkarKedi) {
                    // Satuan PALET -> jumlah baris, bukan sum('jumlah')
                    $totalHasil = $produksi->detailBongkarKedi->count();
                    $detailItems = $produksi->detailBongkarKedi;
                }
                $labelDivisi = 'KEDI (BONGKAR)';
            } elseif ($produksi->status === 'masuk') {
                if ($produksi->detailMasukKedi) {
                    $totalHasil = $produksi->detailMasukKedi->sum('jumlah');
                    $detailItems = $produksi->detailMasukKedi;
                }
                $labelDivisi = 'KEDI (MASUK)';
            }

            // Tambah info kayu ke label
            $firstItem = $detailItems->first();
            if ($firstItem) {
                $infoKayu = $firstItem->jenisKayu->nama_kayu ?? '';
                if ($infoKayu) {
                    $labelDivisi .= ' - '.$infoKayu;
                }
            }

            // --- B. HITUNG POTONGAN ---
            $jumlahPekerja = $produksi->detailPegawaiKedi ? $produksi->detailPegawaiKedi->count() : 0;
            $tanggalStr = Carbon::parse($produksi->tanggal_produksi)->format('Y-m-d');

            $potonganPerOrang = 0; // dipakai jalur lama (masuk) — sama untuk semua pegawai
            $potonganPerPegawai = []; // dipakai jalur baru (bongkar) — keyed by id_pegawai

            if ($produksi->status === 'bongkar') {
                // --- JALUR BARU: gunakan Action/Service terpusat (sesuai target_bongkar_analysis.md) ---

                $mesinEnum = Mesin::Bongkar;
                $resolver = TargetResolverFactory::make($mesinEnum);
                $targetModel = $resolver->resolve($mesinEnum->value);

                if (! $targetModel) {
                    Log::warning("⚠️ [KEDI DEBUG] Target BONGKAR (id_mesin=7) TIDAK DITEMUKAN untuk ID Produksi {$produksi->id}");
                }

                $jamKerjaNormal = $targetModel->jam ?? 0;
                $jamNormalMenit = $jamKerjaNormal * 60;

                // Bangun input per pekerja (durasi kerja aktual dalam menit)
                $pekerjaInputs = [];

                if ($produksi->detailPegawaiKedi) {
                    foreach ($produksi->detailPegawaiKedi as $dp) {
                        $grossMenit = $jamNormalMenit; // fallback kalau masuk/pulang kosong

                        if (! empty($dp->masuk) && ! empty($dp->pulang)) {
                            $masuk = Carbon::parse($tanggalStr.' '.$dp->masuk);
                            $pulang = Carbon::parse($tanggalStr.' '.$dp->pulang);

                            if ($pulang->lessThan($masuk)) {
                                $pulang->addDay();
                            }

                            $grossMenit = $masuk->diffInMinutes($pulang);
                        }

                        $pekerjaInputs[] = new PekerjaKerjaInput(
                            idPegawai: (string) $dp->id_pegawai,
                            menitKerja: (float) max(0, $grossMenit),
                        );
                    }
                }

                if ($targetModel && count($pekerjaInputs) > 0) {
                    $hitung = $action->execute(
                        mesin: $mesinEnum,
                        strategi: StrategiPembagian::Kolektif,
                        pekerja: $pekerjaInputs,
                        hasilAktual: (float) $totalHasil,
                    );

                    $potonganPerPegawai = $hitung?->potonganPerPegawai ?? [];
                }

            } else {
                // --- JALUR LAMA: MASUK / status lain ---
                // TODO: pindah ke resolver terpusat begitu target MASUK/KEDI
                // punya id_mesin sendiri di enum Mesin & tabel targets.
                $kodeTargetDicari = $produksi->status === 'masuk' ? 'MASUK' : 'KEDI';
                $targetRef = Target::where('kode_ukuran', $kodeTargetDicari)->first();

                $stdTarget = (int) ($targetRef->target ?? 0);
                $stdPotHarga = (int) ($targetRef->potongan ?? 0);

                if (! $targetRef) {
                    Log::warning("⚠️ [KEDI DEBUG] Target dengan kode '{$kodeTargetDicari}' TIDAK DITEMUKAN untuk ID Produksi {$produksi->id}");
                }

                $selisih = $totalHasil - $stdTarget;

                if ($stdTarget > 0 && $selisih < 0 && $stdPotHarga > 0 && $jumlahPekerja > 0) {
                    $kekurangan = abs($selisih);
                    $totalPot = $kekurangan * $stdPotHarga;
                    $potonganRaw = $totalPot / $jumlahPekerja;

                    // Pembulatan 3 Tingkat (dipertahankan sementara utk jalur lama)
                    $ribuan = floor($potonganRaw / 1000);
                    $ratusan = $potonganRaw % 1000;

                    if ($ratusan < 300) {
                        $potonganPerOrang = $ribuan * 1000;
                    } elseif ($ratusan >= 300 && $ratusan < 800) {
                        $potonganPerOrang = ($ribuan * 1000) + 500;
                    } else {
                        $potonganPerOrang = ($ribuan + 1) * 1000;
                    }
                }
            }

            // --- C. MAPPING PEGAWAI ---
            if ($produksi->detailPegawaiKedi) {
                foreach ($produksi->detailPegawaiKedi as $dp) {
                    if (! $dp->pegawai) {
                        continue;
                    }

                    $jamMasuk = $dp->masuk ? Carbon::parse($dp->masuk)->format('H:i:s') : '';
                    $jamPulang = $dp->pulang ? Carbon::parse($dp->pulang)->format('H:i:s') : '';

                    if ($produksi->status === 'bongkar') {
                        $potonganFinal = $dp->potongan ?? ($potonganPerPegawai[(string) $dp->id_pegawai] ?? 0);
                    } else {
                        $potonganFinal = $dp->potongan ?? $potonganPerOrang;
                    }

                    $results[] = [
                        'kodep' => $dp->pegawai->kode_pegawai ?? '-',
                        'nama' => $dp->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelDivisi,
                        'ijin' => $dp->ijin ?? '',
                        'potongan_targ' => (int) $potonganFinal,
                        'keterangan' => $dp->keterangan ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
