<?php

namespace App\Services;

use App\Models\HargaPegawai;
use App\Models\Pegawai;
use App\Models\PengajuanPotongan;
use App\Models\PengajuanPotonganHistory;
use App\Models\User;
use App\Services\Payroll\PeriodeJumatKamisResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PengajuanPotonganService
{
    protected static ?float $cachedDefaultHarga = null;

    /**
     * Hitung estimasi gaji bersih karyawan harian berdasarkan master harga pegawai
     * dikurangi usulan potongan yang diajukan.
     */
    public function hitungEstimasiGaji(Pegawai|int|string|null $pegawai, float $nominalPotonganBaru): float
    {
        $gajiDasar = null;

        if ($pegawai instanceof Pegawai) {
            $gajiDasar = isset($pegawai->gaji) && $pegawai->gaji !== null ? (float) $pegawai->gaji : null;
        } elseif (is_numeric($pegawai) && $pegawai > 0) {
            $pegawaiModel = Pegawai::find($pegawai);
            if ($pegawaiModel && isset($pegawaiModel->gaji) && $pegawaiModel->gaji !== null) {
                $gajiDasar = (float) $pegawaiModel->gaji;
            }
        }

        if ($gajiDasar === null || $gajiDasar <= 0) {
            if (self::$cachedDefaultHarga === null) {
                self::$cachedDefaultHarga = (float) (HargaPegawai::first()->harga ?? 115000);
            }
            $gajiDasar = self::$cachedDefaultHarga;
        }

        return max(0, $gajiDasar - $nominalPotonganBaru);
    }

    /**
     * Buat pengajuan baru atau update jika pengajuan sebelumnya masih pending.
     */
    public function buatPengajuan(
        User $user,
        Pegawai $pegawai,
        string $tanggal,
        float $nominalAwal,
        float $nominalUsulan,
        string $alasan,
        ?string $divisi = null
    ): PengajuanPotongan {
        if ($nominalUsulan < 0) {
            throw new \InvalidArgumentException('Nominal usulan potongan tidak boleh negatif.');
        }

        return DB::transaction(function () use ($user, $pegawai, $tanggal, $nominalAwal, $nominalUsulan, $alasan, $divisi) {
            $estimasiGaji = $this->hitungEstimasiGaji($pegawai, $nominalUsulan);

            // Cek apakah ada pengajuan pending untuk pegawai dan tanggal yang sama
            $existing = PengajuanPotongan::where('id_pegawai', $pegawai->id)
                ->whereDate('tanggal', $tanggal)
                ->where('status', 'pending')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $nominalSebelumnya = (float) $existing->nominal_usulan;
                $existing->update([
                    'nominal_usulan' => $nominalUsulan,
                    'estimasi_gaji' => $estimasiGaji,
                    'alasan_pengajuan' => $alasan,
                    'divisi' => $divisi ?: $existing->divisi,
                    'diajukan_oleh' => $user->id,
                ]);

                PengajuanPotonganHistory::create([
                    'pengajuan_potongan_id' => $existing->id,
                    'user_id' => $user->id,
                    'aksi' => 'revise',
                    'nominal_sebelumnya' => $nominalSebelumnya,
                    'nominal_baru' => $nominalUsulan,
                    'catatan' => 'Pengajuan diperbarui oleh pengaju: '.$alasan,
                ]);

                return $existing->fresh();
            }

            $pengajuan = PengajuanPotongan::create([
                'id_pegawai' => $pegawai->id,
                'kode_pegawai' => $pegawai->kode_pegawai,
                'tanggal' => $tanggal,
                'divisi' => $divisi,
                'nominal_awal' => $nominalAwal,
                'nominal_usulan' => $nominalUsulan,
                'nominal_final' => null,
                'estimasi_gaji' => $estimasiGaji,
                'alasan_pengajuan' => $alasan,
                'status' => 'pending',
                'diajukan_oleh' => $user->id,
            ]);

            PengajuanPotonganHistory::create([
                'pengajuan_potongan_id' => $pengajuan->id,
                'user_id' => $user->id,
                'aksi' => 'create',
                'nominal_sebelumnya' => $nominalAwal,
                'nominal_baru' => $nominalUsulan,
                'catatan' => 'Pengajuan potongan dibuat: '.$alasan,
            ]);

            return $pengajuan;
        });
    }

    /**
     * Revisi nominal oleh Super Admin / Absen sebelum atau sekaligus persetujuan.
     */
    public function revisiNominal(
        PengajuanPotongan $pengajuan,
        User $user,
        float $nominalBaru,
        string $catatanRevisi
    ): PengajuanPotongan {
        if ($pengajuan->status !== 'pending') {
            throw new \DomainException('Hanya pengajuan pending yang dapat direvisi.');
        }

        if ($nominalBaru < 0) {
            throw new \InvalidArgumentException('Nominal potongan baru tidak boleh negatif.');
        }

        return DB::transaction(function () use ($pengajuan, $user, $nominalBaru, $catatanRevisi) {
            $nominalLama = (float) $pengajuan->nominal_usulan;

            $pengajuan->update([
                'nominal_usulan' => $nominalBaru,
                'estimasi_gaji' => $this->hitungEstimasiGaji($pengajuan->id_pegawai, $nominalBaru),
            ]);

            PengajuanPotonganHistory::create([
                'pengajuan_potongan_id' => $pengajuan->id,
                'user_id' => $user->id,
                'aksi' => 'revise',
                'nominal_sebelumnya' => $nominalLama,
                'nominal_baru' => $nominalBaru,
                'catatan' => $catatanRevisi,
            ]);

            return $pengajuan;
        });
    }

    /**
     * Setujui pengajuan potongan.
     */
    public function approve(
        PengajuanPotongan $pengajuan,
        User $user,
        ?float $nominalFinal = null,
        ?string $catatan = null
    ): PengajuanPotongan {
        return DB::transaction(function () use ($pengajuan, $user, $nominalFinal, $catatan) {
            // Lock row for update to prevent race conditions
            $record = PengajuanPotongan::where('id', $pengajuan->id)->lockForUpdate()->firstOrFail();

            if ($record->status !== 'pending') {
                throw new \DomainException('Pengajuan ini sudah pernah diproses sebelumnya.');
            }

            $final = $nominalFinal !== null ? $nominalFinal : (float) $record->nominal_usulan;
            if ($final < 0) {
                throw new \InvalidArgumentException('Nominal final tidak boleh negatif.');
            }

            $catatanPersetujuan = $catatan ?: 'Pengajuan potongan disetujui.';

            // Catat jika ada perubahan nominal saat approval
            if ($nominalFinal !== null && $nominalFinal != $record->nominal_usulan) {
                PengajuanPotonganHistory::create([
                    'pengajuan_potongan_id' => $record->id,
                    'user_id' => $user->id,
                    'aksi' => 'revise',
                    'nominal_sebelumnya' => $record->nominal_usulan,
                    'nominal_baru' => $final,
                    'catatan' => 'Revisi nominal oleh admin sebelum disetujui: '.$catatanPersetujuan,
                ]);
            }

            $record->update([
                'nominal_final' => $final,
                'estimasi_gaji' => $this->hitungEstimasiGaji($record->id_pegawai, $final),
                'status' => 'approved',
                'diproses_oleh' => $user->id,
                'diproses_at' => now(),
            ]);

            PengajuanPotonganHistory::create([
                'pengajuan_potongan_id' => $record->id,
                'user_id' => $user->id,
                'aksi' => 'approve',
                'nominal_sebelumnya' => $record->nominal_usulan,
                'nominal_baru' => $final,
                'catatan' => $catatanPersetujuan,
            ]);

            return $record;
        });
    }

    /**
     * Tolak pengajuan potongan.
     */
    public function reject(
        PengajuanPotongan $pengajuan,
        User $user,
        string $alasanPenolakan
    ): PengajuanPotongan {
        if (trim($alasanPenolakan) === '') {
            throw new \InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        return DB::transaction(function () use ($pengajuan, $user, $alasanPenolakan) {
            $record = PengajuanPotongan::where('id', $pengajuan->id)->lockForUpdate()->firstOrFail();

            if ($record->status !== 'pending') {
                throw new \DomainException('Pengajuan ini sudah pernah diproses sebelumnya.');
            }

            $record->update([
                'status' => 'rejected',
                'diproses_oleh' => $user->id,
                'diproses_at' => now(),
                'alasan_penolakan' => $alasanPenolakan,
            ]);

            PengajuanPotonganHistory::create([
                'pengajuan_potongan_id' => $record->id,
                'user_id' => $user->id,
                'aksi' => 'reject',
                'nominal_sebelumnya' => $record->nominal_usulan,
                'nominal_baru' => $record->nominal_awal,
                'catatan' => 'Ditolak: '.$alasanPenolakan,
            ]);

            return $record;
        });
    }

    /**
     * Mendapatkan mapping potongan approved untuk satu tanggal tertentu.
     * Return: [kode_pegawai => nominal_final]
     */
    public function getApprovedPotonganMap(string $tanggal): array
    {
        return PengajuanPotongan::query()
            ->whereDate('tanggal', $tanggal)
            ->where('status', 'approved')
            ->pluck('nominal_final', 'kode_pegawai')
            ->map(fn ($val) => (int) round((float) $val))
            ->toArray();
    }

    /**
     * Hitung akumulasi potongan mingguan untuk seorang pegawai dalam satu periode Jumat-Kamis.
     * Menggunakan nominal approved jika ada, fallback ke kalkulasi sistem.
     */
    public function getAkumulasiMingguan(string $kodePegawai, string $tanggalAcuan): int
    {
        [$mulai, $akhir] = PeriodeJumatKamisResolver::resolveDefault(Carbon::parse($tanggalAcuan));

        // Ambil rekap harian dari PotonganGajiService yang sudah disesuaikan
        $potonganService = app(PotonganGajiService::class);
        $total = 0;

        $current = Carbon::parse($mulai);
        $endDate = Carbon::parse($akhir);

        while ($current->lte($endDate)) {
            $tgl = $current->toDateString();
            $map = $potonganService->getPotonganMap($tgl);
            $total += $potonganService->resolvePotongan($map, $kodePegawai);
            $current->addDay();
        }

        return $total;
    }
}
