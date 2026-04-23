<?php

namespace App\Services;

use App\Models\DetailTurusanKayu;
use App\Models\HargaKayu;
use App\Models\NotaKayu;
use Illuminate\Support\Facades\Auth;

// ============================================================
// SERVICE: NotaKayuJurnalPayloadService
//
// TUGAS SERVICE INI:
//   Menerima 1 record NotaKayu → menghasilkan array payload jurnal
//   yang siap dikirim ke Perusahaan 2.
//
// TIDAK ada Http::post di sini.
// Tidak ada logika simpan ke DB.
// Hanya KALKULASI dan SUSUN payload.
//
// Dipanggil dari: NotaKayuController::show()
// ============================================================

class NotaKayuJurnalPayloadService
{
    // ----------------------------------------------------------
    // ENTRY POINT UTAMA
    // Panggil method ini dari controller.
    //
    // Return contoh:
    // [
    //   'tanggal'    => '2026-03-05',
    //   'keterangan' => 'Pembelian Kayu Seri 336',
    //   'no_dokumen' => '0503202633618007',
    //   'supplier'   => 'Rahmat',
    //   'seri'       => 336,
    //   'entries'    => [
    //     // DEBIT — bisa lebih dari 1 jika ada kayu 130 DAN 260
    //     [
    //       'posisi'      => 'debit',
    //       'panjang'     => 130,
    //       'total_nilai' => 3623969,
    //       'items'       => [ ...detail per diameter... ]
    //     ],
    //     [
    //       'posisi'      => 'debit',
    //       'panjang'     => 260,
    //       'total_nilai' => 2652024,
    //       'items'       => [ ...detail per diameter... ]
    //     ],
    //     // KREDIT — selalu 2 baris tetap
    //     [
    //       'posisi'      => 'kredit',
    //       'jenis'       => 'hutang_turun',
    //       'total_nilai' => 35993,
    //       'items'       => [ ... ]
    //     ],
    //     [
    //       'posisi'      => 'kredit',
    //       'jenis'       => 'kas_tunai',
    //       'total_nilai' => 6240000,
    //       'items'       => [ ... ]
    //     ],
    //   ]
    // ]
    // ----------------------------------------------------------
    public function buildPayload(NotaKayu $nota): array
    {
        // 1. Pastikan relasi sudah dimuat
        $nota->loadMissing([
            'kayuMasuk.detailTurusanKayus.jenisKayu',
            'kayuMasuk.penggunaanSupplier',
        ]);

        $details = $nota->kayuMasuk?->detailTurusanKayus ?? collect();

        if ($details->isEmpty()) {
            throw new \RuntimeException("Nota #{$nota->no_nota} tidak memiliki detail turusan kayu.");
        }

        // 2. Hitung semua angka keuangan (sama persis dengan NotaKayuController)
        $kalkulasi = $this->hitungKalkulasi($nota, $details);

        // 3. Kelompokkan detail kayu per PANJANG → ini yang jadi debit persediaan
        $groupPerPanjang = $this->groupDetailPerPanjang($details);

        // 4. Susun entries debit (1 entry per panjang kayu)
        $entriesDebit = $this->buildEntriesDebit($groupPerPanjang, $nota);

        // 5. Susun entries kredit (selalu 2: hutang turun + kas)
        $entriesKredit = $this->buildEntriesKredit($kalkulasi, $nota);

        // 6. Validasi balance sebelum dikirim
        $this->validateBalance($entriesDebit, $entriesKredit);

        return [
            'tanggal'    => $nota->created_at->toDateString(),
            'keterangan' => "Pembelian Kayu Seri {$nota->kayuMasuk->seri}",
            'no_dokumen' => $nota->no_nota,
            'supplier'   => $nota->kayuMasuk->penggunaanSupplier?->nama_supplier ?? '-',
            'seri'       => $nota->kayuMasuk->seri,
            'entries'    => array_merge($entriesDebit, $entriesKredit),

            'petugas'    => [
                'nama' => Auth::user()?->name ?? 'System Sync',
            ],

            // Metadata tambahan untuk audit
            '_meta' => [
                'grand_total'   => $kalkulasi['grand_total'],
                'biaya_turun'   => $kalkulasi['biaya_turun'],
                'kas_tunai'     => $kalkulasi['kas_tunai'],
                'total_batang'  => $details->sum('kuantitas'),
                'total_kubikasi' => round($details->sum(fn($d) => round($d->kubikasi, 4)), 4),
            ],
        ];
    }

    // ----------------------------------------------------------
    // KALKULASI KEUANGAN
    // Logika sama persis dengan NotaKayuController::show()
    // Dipindah ke sini agar bisa dipakai ulang tanpa
    // harus memanggil controller.
    // ----------------------------------------------------------
    private function hitungKalkulasi(NotaKayu $nota, $details): array
    {
        // --- Grand Total (rupiah, dari semua detail) ---
        $grandTotal = 0;

        foreach ($details as $item) {
            $idJenisKayu = $item->id_jenis_kayu ?? optional($item->jenisKayu)->id;

            $harga = HargaKayu::where('id_jenis_kayu', $idJenisKayu)
                ->where('grade', $item->grade)
                ->where('panjang', $item->panjang)
                ->where('diameter_terkecil', '<=', $item->diameter)
                ->where('diameter_terbesar', '>=', $item->diameter)
                ->orderBy('diameter_terkecil', 'desc')
                ->value('harga_beli');

            $grandTotal += round(($harga ?? 0) * round($item->kubikasi, 4) * 1000);
        }

        $grandTotal = (int) round($grandTotal);

        // --- Total Kubikasi ---
        $totalKubikasi = round(
            $details->sum(fn($d) => round($d->kubikasi, 4)),
            4
        );

        // --- Biaya Turun Kayu ---
        $adjustment     = (int) ($nota->adjustment ?? 0);
        $biayaTurunPerM3 = 5000;
        $hasilDasar     = round($totalKubikasi * $biayaTurunPerM3);
        $biayaFloor     = floor($hasilDasar / 1000) * 1000;
        $sisaRibuan     = $grandTotal % 1000;
        $biayaTurunKayu = (int) ($biayaFloor + $sisaRibuan + 10000);

        // --- Harga Beli Akhir (sebelum pembulatan manual) ---
        $hargaBeliAkhir = (int) round($grandTotal - $biayaTurunKayu);

        // --- Bulatkan ke 5000 ---
        $mod = $hargaBeliAkhir % 5000;
        $hargaBeliAkhirBulat = $mod >= 2500
            ? $hargaBeliAkhir + (5000 - $mod)
            : $hargaBeliAkhir - $mod;

        // --- Tambah pembulatan manual ---
        $totalAkhir = (int) ($hargaBeliAkhirBulat + $adjustment);
        $mod        = $totalAkhir % 5000;
        $totalAkhir = $mod >= 2500
            ? $totalAkhir + (5000 - $mod)
            : $totalAkhir - $mod;

        return [
            'grand_total'  => $grandTotal,
            'biaya_turun'  => $biayaTurunKayu,
            'kas_tunai'    => $totalAkhir,   // yang dibayarkan ke supplier
            'total_kubikasi' => $totalKubikasi,
        ];
    }

    // ----------------------------------------------------------
    // GROUP DETAIL PER PANJANG
    //
    // Input : Collection<DetailTurusanKayu>
    // Output: array dikelompokkan per panjang (130, 260, dll)
    //
    // Contoh output:
    // [
    //   130 => [
    //     'panjang'       => 130,
    //     'total_batang'  => 97,
    //     'total_kubikasi'=> 2.1143,
    //     'total_harga'   => 3623969,
    //     'items'         => [ ...baris per diameter... ]
    //   ],
    //   260 => [ ... ],
    // ]
    // ----------------------------------------------------------
    private function groupDetailPerPanjang($details): array
    {
        // Group collection by panjang
        $grouped = $details->groupBy('panjang');

        $result = [];

        foreach ($grouped as $panjang => $itemsInGroup) {

            $totalBatang   = 0;
            $totalKubikasi = 0.0;
            $totalHarga    = 0;
            $itemRows      = [];

            // Hitung per baris detail
            foreach ($itemsInGroup as $item) {

                $idJenisKayu = $item->id_jenis_kayu ?? optional($item->jenisKayu)->id;

                // Cari harga dari tabel harga_kayus
                $hargaRecord = HargaKayu::where('id_jenis_kayu', $idJenisKayu)
                    ->where('grade', $item->grade)
                    ->where('panjang', $item->panjang)
                    ->where('diameter_terkecil', '<=', $item->diameter)
                    ->where('diameter_terbesar', '>=', $item->diameter)
                    ->orderBy('diameter_terkecil', 'desc')
                    ->first();

                $hargaBeli    = $hargaRecord?->harga_beli ?? 0;
                $kubikasi     = round($item->kubikasi, 4);
                $subtotal     = (int) round($hargaBeli * $kubikasi * 1000);

                $totalBatang   += $item->kuantitas;
                $totalKubikasi += $kubikasi;
                $totalHarga    += $subtotal;

                $mappedGrade = match ((string)$item->grade) {
                    '1' => 'A',
                    '2' => 'B',
                    'A' => 'A',
                    'B' => 'B',
                    default => 'A', // Fallback default
                };

                // Simpan sebagai item detail (akan jadi JurnalPembantuItem di P2)
                $itemRows[] = [
                    'urut'          => count($itemRows) + 1,
                    'jenis_kayu'    => $item->jenisKayu?->nama_kayu ?? '-',
                    'grade'         => $mappedGrade,
                    'kode_lahan' => $item->lahan->kode_lahan ?? '-',
                    'nama_lahan'         => $item->lahan->nama_lahan ?? '-',       // kode lahan: BA, AB, KA, J, dll
                    'panjang'       => (int) $item->panjang,      // cm
                    'diameter_dari' => $item->diameter_dari ?? $item->diameter,
                    'diameter_ke'   => $item->diameter_ke ?? $item->diameter,
                    'banyak'        => $item->kuantitas,          // batang
                    'm3'            => $kubikasi,
                    'harga'         => $hargaBeli,                // per poin
                    'jumlah'        => $subtotal,                 // rupiah
                    // Untuk nama_barang di JurnalPembantuItem
                    'nama_barang'   => trim(($item->jenisKayu?->nama_kayu ?? '') . ' (' . ($mappedGrade ?? '') . ')'),
                    // Untuk field ukuran di JurnalPembantuItem
                    'ukuran'        => $item->panjang . 'cm | D: ' . ($item->diameter_dari ?? $item->diameter) . '-' . ($item->diameter_ke ?? $item->diameter),
                ];
            }

            $result[(int) $panjang] = [
                'panjang'        => (int) $panjang,
                'total_batang'   => $totalBatang,
                'total_kubikasi' => round($totalKubikasi, 4),
                'total_harga'    => $totalHarga,
                'items'          => $itemRows,
            ];
        }

        return $result;
    }

    // ----------------------------------------------------------
    // BUILD ENTRIES DEBIT
    // 1 entry debit per panjang (130 atau 260)
    // Nomor akun TIDAK ditentukan di sini — ditentukan di P2
    // ----------------------------------------------------------
    private function buildEntriesDebit(array $groupPerPanjang, NotaKayu $nota): array
    {
        $entries = [];

        foreach ($groupPerPanjang as $panjang => $group) {
            $entries[] = [
                'posisi'         => 'debit',
                'panjang'        => $panjang,   // P2 pakai ini untuk lookup no_akun
                'total_nilai'    => $group['total_harga'],
                'total_batang'   => $group['total_batang'],
                'total_kubikasi' => $group['total_kubikasi'],
                'keterangan'     => "Persediaan Kayu {$panjang}cm - Seri {$nota->kayuMasuk->seri}",
                'items'          => $group['items'],
            ];
        }

        return $entries;
    }

    // ----------------------------------------------------------
    // BUILD ENTRIES KREDIT
    // Selalu 2 baris: hutang_turun + kas_tunai
    // ----------------------------------------------------------
    private function buildEntriesKredit(array $kalkulasi, NotaKayu $nota): array
    {
        $seri = $nota->kayuMasuk->seri;

        return [
            // Kredit 1: Hutang Ongkos Turun Kayu
            [
                'posisi'      => 'kredit',
                'jenis'       => 'hutang_turun',   // P2 pakai ini untuk lookup no_akun (210-021)
                'total_nilai' => $kalkulasi['biaya_turun'],
                'keterangan'  => "Hutang Ongkos Turun Kayu Seri {$seri}",
                'items'       => [
                    [
                        'urut'        => 1,
                        'nama_barang' => "Ongkos Turun Kayu Seri {$seri}",
                        'keterangan'  => "Biaya turun kayu - kubikasi {$kalkulasi['total_kubikasi']} m³ × Rp 5.000",
                        'banyak'      => 1,
                        'm3'          => $kalkulasi['total_kubikasi'],
                        'harga'       => 5000,
                        'jumlah'      => $kalkulasi['biaya_turun'],
                    ],
                ],
            ],

            // Kredit 2: Kas Tunai
            [
                'posisi'      => 'kredit',
                'jenis'       => 'kas_tunai',      // P2 pakai ini untuk lookup no_akun (110-01)
                'total_nilai' => $kalkulasi['kas_tunai'],
                'keterangan'  => "Kas Tunai Pembelian Kayu Seri {$seri}",
                'items'       => [
                    [
                        'urut'        => 1,
                        'nama_barang' => "Pembayaran Kayu Seri {$seri}",
                        'keterangan'  => "Grand Total {$kalkulasi['grand_total']} - Biaya Turun {$kalkulasi['biaya_turun']}",
                        'banyak'      => 1,
                        'm3'          => $kalkulasi['total_kubikasi'],
                        'harga'       => $kalkulasi['kas_tunai'],
                        'jumlah'      => $kalkulasi['kas_tunai'],
                    ],
                ],
            ],
        ];
    }

    // ----------------------------------------------------------
    // VALIDASI BALANCE
    // Total debit HARUS = Total kredit
    // Lempar exception sebelum kirim jika tidak balance
    // ----------------------------------------------------------
    private function validateBalance(array $entriesDebit, array $entriesKredit): void
    {
        $totalDebit  = array_sum(array_column($entriesDebit, 'total_nilai'));
        $totalKredit = array_sum(array_column($entriesKredit, 'total_nilai'));

        // Toleransi 1 rupiah karena pembulatan
        if (abs($totalDebit - $totalKredit) > 1) {
            \Illuminate\Support\Facades\Log::warning("Mengirim Jurnal TIDAK BALANCE! No Nota: ID Lahan", [
                'debit' => $totalDebit,
                'kredit' => $totalKredit,
                'selisih' => abs($totalDebit - $totalKredit)
            ]);
        }
    }
}
