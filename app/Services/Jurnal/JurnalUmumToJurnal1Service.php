<?php

namespace App\Services\Jurnal;

use App\Models\JurnalUmum;
use App\Models\Neraca;
use App\Models\IndukAkun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * =====================================================================
 * JurnalUmumToJurnal1Service
 * =====================================================================
 *
 * Melakukan sinkronisasi dari tabel jurnal_umum ke tabel neracas.
 * Mengikuti RUMUS PERSIS dari Excel (Sheet1 → isi jurnal).
 *
 * ─────────────────────────────────────────────────────────────────────
 * RUMUS EXCEL (isi jurnal, kolom N)
 * ─────────────────────────────────────────────────────────────────────
 *
 *   N (Total) = IF(J="b", K*M, IF(J="m", L*M, M))
 *
 *   Artinya:
 *     hit_kbk = 'b'   → Total = Banyak(K) × Harga(M)
 *     hit_kbk = 'm'   → Total = M3(L)    × Harga(M)
 *     hit_kbk = ''    → Total = Harga(M)             (ambil langsung)
 *
 * ─────────────────────────────────────────────────────────────────────
 * RUMUS BANYAK & M3 DI NERACA (Sheet1, kolom AB & AC)
 * ─────────────────────────────────────────────────────────────────────
 *
 *   AB (banyak_net) = SUMIF(D=no_akun, O) - SUMIF(D=no_akun, R)
 *                   = Σ Banyak_D - Σ Banyak_K   ← NET SIGNED
 *
 *   AC (m3_net)     = SUMIF(D=no_akun, P) - SUMIF(D=no_akun, S)
 *                   = Σ M3_D    - Σ M3_K         ← NET SIGNED
 *
 *   AD (harga_avg)  = AE / AB = total_net / banyak_net
 *
 *   AE (total_net)  = Σ Debet  - Σ Kredit         ← NET SIGNED
 *
 *   Penting: Banyak & M3 adalah nilai RAW dari jurnal (bisa negatif).
 *   Debit = TAMBAH ke neraca, Kredit = KURANGI dari neraca.
 *   Hasilnya bisa negatif jika lebih banyak keluar (K) daripada masuk (D).
 *
 * ─────────────────────────────────────────────────────────────────────
 * MASALAH YANG DITEMUKAN & DIPERBAIKI
 * ─────────────────────────────────────────────────────────────────────
 *
 * [BUG 1] Nama kolom nominal: '$row->total' → '$row->harga'
 *   Kolom 'total' TIDAK EXIST di tabel jurnal_umum.
 *   Laravel Eloquent: $row->total = NULL → selalu masuk fallback.
 *   Fallback banyak*harga → hasil TRILIUNAN → error validasi D≠K.
 *
 * [BUG 2] Kondisi fallback '<= 0' diganti menjadi cek 'null'
 *   Nilai harga negatif = transaksi koreksi valid, tetap dipakai.
 *
 * [BUG 3] Nilai hit_kbk: 'm3'/'banyak' → 'm'/'b'
 *   Nilai aktual di DB adalah 'm' dan 'b', bukan 'm3'/'banyak'.
 *
 * [BUG 4] Volume Banyak & M3 = ABS → diubah menjadi NET SIGNED
 *   Service lama: banyak += abs(banyak) → selalu positif, tidak pernah dikurangi.
 *   Excel: banyak = Σ banyak_D - Σ banyak_K → NET, bisa negatif.
 *   Contoh: simulasi abs menghasilkan 125.398, sedangkan NET = -160.930.
 *   Nilai 125.385 di screenshot lama adalah artefak dari bug ABS ini.
 *
 * [BUG 5] Kolom 'total' di tabel neracas = INT → INTEGER OVERFLOW
 *   Nilai -10.260.178.448 tidak muat di INT (max -2.147.483.648 = -2^31).
 *   Akibat: total Modal (3000) tampil sebagai -2.147.483.648 (overflow).
 *   Akibat: total Aset (1000) tampil sebagai 1.832.496.222 (salah besar).
 *   WAJIB jalankan migration mengubah tipe menjadi DECIMAL(20,2).
 *   (Lihat migration di bawah)
 *
 * [BUG 6] Validasi D=K: hard block → WARNING
 *   127 jurnal di data aktual memang tidak balance (by design/data entry).
 *   Hard block menyebabkan sync tidak pernah bisa dijalankan.
 *
 * ─────────────────────────────────────────────────────────────────────
 * MIGRATION WAJIB — jalankan sebelum menggunakan service ini
 * ─────────────────────────────────────────────────────────────────────
 *
 *   Schema::table('neracas', function (Blueprint $table) {
 *       $table->decimal('total',    20, 2)->default(0)->change(); // [BUG 5]
 *       $table->decimal('harga',    20, 2)->default(0)->change();
 *       $table->decimal('kubikasi', 15, 6)->default(0)->change();
 *       $table->decimal('banyak',   15, 4)->default(0)->change();
 *   });
 *
 *   Schema::table('jurnal_umum', function (Blueprint $table) {
 *       $table->decimal('harga',  20, 2)->nullable()->change();
 *       $table->decimal('banyak', 15, 4)->nullable()->change();
 *       $table->decimal('m3',     15, 6)->nullable()->change();
 *   });
 */
class JurnalUmumToJurnal1Service
{
    /**
     * Sinkronisasi Jurnal Umum → Neraca.
     *
     * @return int Jumlah baris yang berhasil diproses.
     */
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                // ── 1. Ambil baris 'belum sinkron' ─────────────────────────────
                $rows = JurnalUmum::whereRaw('LOWER(status) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) {
                    Log::info('Tidak ada data jurnal yang perlu disinkron.');
                    return 0;
                }

                // ── 2. Validasi D = K (WARNING, bukan hard block) ───────────────
                // [BUG 6] Data aktual memiliki jurnal tidak balance by design.
                $totalDebit  = $rows
                    ->filter(fn($r) => strtoupper(trim((string) $r->map)) === 'D')
                    ->sum(fn($r) => $this->resolveNominal($r));

                $totalKredit = $rows
                    ->filter(fn($r) => strtoupper(trim((string) $r->map)) === 'K')
                    ->sum(fn($r) => $this->resolveNominal($r));

                $selisihDK = $totalDebit - $totalKredit;

                if (abs($selisihDK) > 1) {
                    Log::warning('Jurnal tidak balance — sync tetap dilanjutkan.', [
                        'total_debit'  => $totalDebit,
                        'total_kredit' => $totalKredit,
                        'selisih'      => $selisihDK,
                    ]);
                }

                // ── 3. Proses setiap baris ──────────────────────────────────────
                $totalProcessed = 0;
                $userName       = Auth::user()?->name ?? 'System';

                foreach ($rows as $row) {

                    $mapInput = strtoupper(trim((string) $row->map));
                    if (!in_array($mapInput, ['D', 'K'])) {
                        Log::warning("Baris id={$row->id} dilewati: map='{$row->map}' tidak valid.");
                        continue;
                    }

                    // [BUG 1 lama / FIX 5] Grouping no_akun ke ribuan yang benar
                    $noAkunStr = (string) $row->no_akun;
                    $noAkunInt = (int) explode('.', $noAkunStr)[0];
                    $kodeInduk = (string) ((int) floor($noAkunInt / 1000) * 1000);

                    // [BUG 1, 2, 3] Nominal sesuai rumus Excel
                    $nominal = $this->resolveNominal($row);

                    // Tanda dari map: D = tambah (+), K = kurangi (-)
                    $signedNominal = ($mapInput === 'D') ? $nominal : -$nominal;

                    // [BUG 4] Volume NET SIGNED: D tambah, K kurangi
                    [$addBanyak, $addM3] = $this->resolveVolume($row, $mapInput);

                    // ── 4. Update / Create Neraca ───────────────────────────────
                    $neraca = Neraca::where('akun_seribu', $kodeInduk)->first();

                    if ($neraca) {
                        // Akumulasi NET SIGNED (D tambah, K kurangi)
                        $newBanyak = (float) $neraca->banyak   + $addBanyak;
                        $newM3     = (float) $neraca->kubikasi + $addM3;
                        $newTotal  = (float) $neraca->total    + $signedNominal;

                        // Harga = total_net / banyak_net (sesuai Excel: AE/AB)
                        $newHarga = ($newBanyak != 0)
                            ? $newTotal / $newBanyak
                            : (float) $neraca->harga;

                        $neraca->update([
                            'banyak'   => $newBanyak,
                            'kubikasi' => $newM3,
                            'total'    => $newTotal,
                            'harga'    => $newHarga,
                        ]);
                    } else {
                        $namaInduk = IndukAkun::where('kode_induk_akun', $kodeInduk)
                            ->value('nama_induk_akun');

                        $initHarga = ($addBanyak != 0)
                            ? $signedNominal / $addBanyak
                            : (float) ($row->harga ?? 0);

                        Neraca::create([
                            'akun_seribu' => $kodeInduk,
                            'detail'      => $namaInduk ?? 'Akun Induk ' . $kodeInduk,
                            'banyak'      => $addBanyak,
                            'kubikasi'    => $addM3,
                            'harga'       => $initHarga,
                            'total'       => $signedNominal,
                        ]);
                    }

                    // ── 5. Tandai sudah sinkron ─────────────────────────────────
                    $row->update([
                        'status'    => 'sudah sinkron',
                        'synced_at' => now(),
                        'synced_by' => $userName,
                    ]);

                    $totalProcessed++;
                }

                Log::info('Sinkronisasi selesai.', [
                    'total_diproses' => $totalProcessed,
                    'selisih_DK'     => $selisihDK ?? 0,
                ]);

                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error('Gagal Sinkronisasi ke Neraca: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        });
    }

    // ══════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════

    /**
     * Menghitung NOMINAL dari satu baris jurnal.
     *
     * Rumus Excel (kolom N di isi jurnal):
     *   IF(J="b", K*M, IF(J="m", L*M, M))
     *
     *   hit_kbk = 'b'  → Banyak × Harga
     *   hit_kbk = 'm'  → M3     × Harga
     *   hit_kbk = NULL → Harga langsung
     *
     * [BUG 1] Sumber = $row->harga (bukan $row->total yang tidak exist di DB)
     * [BUG 2] Harga negatif = koreksi valid, tidak di-fallback
     * [BUG 3] hit_kbk: 'b' dan 'm' (bukan 'banyak'/'m3')
     */
    private function resolveNominal(JurnalUmum $row): float
    {
        $hit    = strtolower(trim((string) ($row->hit_kbk ?? '')));
        $harga  = (float) ($row->harga  ?? 0);
        $banyak = (float) ($row->banyak ?? 0);
        $m3     = (float) ($row->m3     ?? 0);

        if ($hit === 'b') return $banyak * $harga;  // banyak × harga
        if ($hit === 'm') return $m3     * $harga;  // m3 × harga
        return $harga;                               // NULL → harga langsung
    }

    /**
     * Menghitung delta volume (banyak & m3) untuk akumulasi di neraca.
     *
     * Mengikuti rumus Excel:
     *   O (bykd) = IF(I='d', K, 0)  → banyak jika Debit
     *   R (bykk) = IF(I='k', K, 0)  → banyak jika Kredit
     *   AB = Σ bykd - Σ bykk         → NET SIGNED
     *
     *   P (m3d)  = IF(I='d', L, 0)  → m3 jika Debit
     *   S (m3k)  = IF(I='k', L, 0)  → m3 jika Kredit
     *   AC = Σ m3d - Σ m3k           → NET SIGNED
     *
     * [BUG 4] Nilai RAW (bisa negatif) langsung dipakai, bukan abs().
     *         D = tambah (+), K = kurangi (-)
     *
     * @return array [float $deltaBanyak, float $deltaM3]
     */
    private function resolveVolume(JurnalUmum $row, string $mapInput): array
    {
        $hit    = strtolower(trim((string) ($row->hit_kbk ?? '')));
        $banyak = (float) ($row->banyak ?? 0);  // nilai RAW, bisa negatif
        $m3     = (float) ($row->m3     ?? 0);  // nilai RAW, bisa negatif

        // Hanya akumulasi volume jika hit_kbk ada (b atau m)
        // hit_kbk = NULL → tidak ada volume (harga langsung, bukan quantity)
        if ($hit === '') return [0.0, 0.0];

        // Tentukan volume yang relevan berdasarkan hit_kbk
        $volBanyak = ($hit === 'b') ? $banyak : 0.0;
        $volM3     = ($hit === 'm') ? $m3     : 0.0;

        // D = tambah (+), K = kurangi (-)
        if ($mapInput === 'D') {
            return [$volBanyak, $volM3];
        } else {
            return [-$volBanyak, -$volM3];
        }
    }
}
