<?php

namespace App\Http\Controllers;

use App\Models\HasilSanding;
use App\Models\SerahTerimaHp;
use App\Models\ProduksiSanding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SandingApiController extends Controller
{
    /**
     * 1. GET: Mengambil daftar antrean sanding yang belum selesai.
     */
    public function index(Request $request)
    {
        $tipeSanding   = $request->query('tipe', 'besar'); // 'besar' atau 'kecil'
        $namaMesin     = 'Sanding ' . ucfirst($tipeSanding);
        $tanggalFilter = $request->query('tanggal', date('Y-m-d'));

        // Query data SerahTerimaHp dengan relasi lengkap
        $query = SerahTerimaHp::with([
            'triplekMutasiKeluar.jenisKayu',
            'platformMthMutasiKeluar.jenisKayu',
            'triplekMthMutasiKeluar.jenisKayu',
            'platformHasilHp.barangSetengahJadi.ukuran',
            'platformHasilHp.barangSetengahJadi.grade',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'hasilGrajiTriplek.barangSetengahJadiHp.ukuran',
            'hasilGrajiTriplek.barangSetengahJadiHp.grade',
            'hasilGrajiTriplek.barangSetengahJadiHp.jenisBarang',
            'hasilSanding.barangSetengahJadi.ukuran',
            'hasilSanding.barangSetengahJadi.grade',
            'hasilSanding.barangSetengahJadi.jenisBarang',
        ])
            ->where('tujuan', 'sanding')
            ->where('diterima_oleh', '!=', '-')
            ->whereNull('ditolak_oleh')
            ->whereDate('created_at', $tanggalFilter);

        $serahTerimas = $query->orderBy('id', 'desc')->get();
        $totalItem    = $serahTerimas->count();

        $formattedData = $serahTerimas->map(function ($item, $index) use ($namaMesin, $totalItem) {
            $tebal       = '';
            $inisialKayu = '';
            $namaGrade   = '';

            // Cek dari Mutasi Gudang
            if ($item->id_triplek_mutasi_keluar !== null && $item->triplekMutasiKeluar) {
                $m = $item->triplekMutasiKeluar;
                $tebal       = $m->tebal ? (string)($m->tebal + 0) : '';
                $namaJenis   = $m->jenisKayu?->nama_kayu ?? '';
                $inisialKayu = $namaJenis ? strtoupper(substr(trim($namaJenis), 0, 1)) : '';
                $namaGrade   = $m->kw_grade ?? '';
            } elseif ($item->id_platform_mth_mutasi_keluar !== null && $item->platformMthMutasiKeluar) {
                $m = $item->platformMthMutasiKeluar;
                $tebal       = $m->tebal ? (string)($m->tebal + 0) : '';
                $namaJenis   = $m->jenisKayu?->nama_kayu ?? '';
                $inisialKayu = $namaJenis ? strtoupper(substr(trim($namaJenis), 0, 1)) : '';
                $namaGrade   = $m->kw_grade ?? '';
            } elseif ($item->id_triplek_mth_mutasi_keluar !== null && $item->triplekMthMutasiKeluar) {
                $m = $item->triplekMthMutasiKeluar;
                $tebal       = $m->tebal ? (string)($m->tebal + 0) : '';
                $namaJenis   = $m->jenisKayu?->nama_kayu ?? '';
                $inisialKayu = $namaJenis ? strtoupper(substr(trim($namaJenis), 0, 1)) : '';
                $namaGrade   = $m->kw_grade ?? '';
            }

            // Cek dari Platform Hasil HP
            elseif ($item->platformHasilHp && $item->platformHasilHp->barangSetengahJadi) {
                $b = $item->platformHasilHp->barangSetengahJadi;
                $tebal = $b->ukuran?->tebal ?? '';
                if (!$tebal && $b->ukuran?->dimensi) {
                    $parts = explode('x', str_replace(' ', '', $b->ukuran->dimensi));
                    $tebal = trim(end($parts));
                }
                $namaJenis = $b->jenisBarang?->nama_jenis_barang ?? '';
                if ($namaJenis) {
                    $inisialKayu = strtoupper(substr(trim($namaJenis), 0, 1));
                }
                if ($b->grade) {
                    $namaGrade = $b->grade->nama_grade ?? $b->grade->keterangan ?? '';
                }
            }

            // Cek dari Hasil Graji Triplek
            elseif ($item->hasilGrajiTriplek && $item->hasilGrajiTriplek->barangSetengahJadiHp) {
                $b = $item->hasilGrajiTriplek->barangSetengahJadiHp;
                $tebal = $b->ukuran?->tebal ?? '';
                if (!$tebal && $b->ukuran?->dimensi) {
                    $parts = explode('x', str_replace(' ', '', $b->ukuran->dimensi));
                    $tebal = trim(end($parts));
                }
                $namaJenis = $b->jenisBarang?->nama_jenis_barang ?? '';
                if ($namaJenis) {
                    $inisialKayu = strtoupper(substr(trim($namaJenis), 0, 1));
                }
                if ($b->grade) {
                    $namaGrade = $b->grade->nama_grade ?? $b->grade->keterangan ?? '';
                }
            }

            // Cek dari Hasil Sanding sebelumnya
            elseif ($item->hasilSanding && $item->hasilSanding->barangSetengahJadi) {
                $b = $item->hasilSanding->barangSetengahJadi;
                $tebal = $b->ukuran?->tebal ?? '';
                if (!$tebal && $b->ukuran?->dimensi) {
                    $parts = explode('x', str_replace(' ', '', $b->ukuran->dimensi));
                    $tebal = trim(end($parts));
                }
                $namaJenis = $b->jenisBarang?->nama_jenis_barang ?? '';
                if ($namaJenis) {
                    $inisialKayu = strtoupper(substr(trim($namaJenis), 0, 1));
                }
                if ($b->grade) {
                    $namaGrade = $b->grade->nama_grade ?? $b->grade->keterangan ?? '';
                }
            }

            // Format Ringkas Nama Barang
            $namaBarangRingkas = trim("{$tebal}{$inisialKayu} {$namaGrade}");
            if ($namaBarangRingkas === '') {
                $namaBarangRingkas = 'Barang Umum';
            }

            // Cek data hasil sanding yang sudah ada
            $existingHasil = HasilSanding::where('id_serah_terima_hp', $item->id)->first();

            // PENOMORAN PALET URUT OTOMATIS (1, 2, 3...)
            if ($existingHasil && !empty($existingHasil->no_palet)) {
                $noPaletAngka = (int) $existingHasil->no_palet;
            } else {
                $noPaletAngka = $totalItem - $index;
            }

            // Hitung pengerjaan sisi Face dan Back
            $faceCount = $existingHasil ? $existingHasil->putaran()->where('sisi', 'face')->count() : 0;
            $backCount = $existingHasil ? $existingHasil->putaran()->where('sisi', 'back')->count() : 0;

            // Selesai jika kedua sisi sudah disanding, atau operator menutup manual
            $sudahSelesai = $existingHasil && (
                in_array($existingHasil->status, [
                    'Selesai 1 Sisi',
                    'Selesai 2 Sisi',
                    'Selesai Manual',
                ], true)
                || ($faceCount >= 1 && $backCount >= 1)
            );

            // Penentuan Shift & Tanggal Berdasarkan Waktu
            $timestamp = $item->created_at ?? now();
            $jam       = $timestamp->format('H:i:s');
            $shift     = ($jam >= '06:00:00' && $jam < '16:00:00') ? 'PAGI' : 'MALAM';

            $tanggalProduksi = $timestamp->format('Y-m-d');
            if ($jam >= '00:00:00' && $jam < '06:00:00') {
                $tanggalProduksi = $timestamp->copy()->subDay()->format('Y-m-d');
            }

            // Cari ID Produksi Sanding aktif
            $activeProduksi = ProduksiSanding::where('tanggal', $tanggalProduksi)
                ->where('shift', $shift)
                ->orderBy('id', 'desc')
                ->first();

            $idProduksiSanding = $activeProduksi ? $activeProduksi->id : null;

            return [
                'id'                  => $item->id,
                'id_produksi_sanding' => $idProduksiSanding,
                'tanggal'             => $tanggalProduksi,
                'mesin'               => $namaMesin,
                'shift'               => $shift,
                'no_palet'            => $noPaletAngka,
                'nama_barang'         => $namaBarangRingkas,
                'target'              => $item->sisa ?? 0,
                'jumlah_sanding_face' => $faceCount,
                'jumlah_sanding_back' => $backCount,
                'sudah_selesai'       => (bool) $sudahSelesai,
            ];
        })
            ->reject(function ($item) {
                return $item['sudah_selesai'] === true;
            })
            ->values();

        return response()->json([
            'status'  => 'success',
            'tipe'    => $tipeSanding,
            'tanggal' => $tanggalFilter,
            'data'    => $formattedData
        ]);
    }

    /**
     * 2. POST: Menyimpan data putaran (Face/Back) untuk mesin sanding.
     */
    public function storePutaran(Request $request, SerahTerimaHp $serahTerimaHp)
    {
        $v = $request->validate([
            'sisi'                => 'required|in:face,back',
            'putaran_ke'          => 'required|integer|min:1|max:2',
            'modal'               => 'required|integer|min:0',
            'masuk'               => 'required|integer|min:0',
            'keluar'              => 'required|integer|min:0',
            'machine_id'          => 'nullable|string|max:100',
            'alasan'              => 'nullable|string|max:50',
            'id_produksi_sanding' => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($serahTerimaHp, $v) {
            $idProduksiSanding = $v['id_produksi_sanding'] ?? null;

            // Ambil ID barang setengah jadi dari relasi
            $idBarangSetengahJadi = null;
            if ($serahTerimaHp->platformHasilHp) {
                $idBarangSetengahJadi = $serahTerimaHp->platformHasilHp->id_barang_setengah_jadi;
            } elseif ($serahTerimaHp->hasilGrajiTriplek) {
                $idBarangSetengahJadi = $serahTerimaHp->hasilGrajiTriplek->id_barang_setengah_jadi;
            } elseif ($serahTerimaHp->hasilSanding) {
                $idBarangSetengahJadi = $serahTerimaHp->hasilSanding->id_barang_setengah_jadi;
            }

            // HITUNG JUMLAH PALET BERDASARKAN SESI PRODUKSI (Auto Increment 1, 2, 3...)
            $existingCount = HasilSanding::where('id_produksi_sanding', $idProduksiSanding)->count();
            $nextNoPalet   = $existingCount + 1;

            // Simpan atau ambil data HasilSanding
            $palet = HasilSanding::firstOrCreate(
                ['id_serah_terima_hp' => $serahTerimaHp->id],
                [
                    'id_produksi_sanding'     => $idProduksiSanding,
                    'id_barang_setengah_jadi' => $idBarangSetengahJadi,
                    'kuantitas'               => $v['modal'],
                    'no_palet'                => $nextNoPalet,
                    'status'                  => 'Belum Selesai',
                ]
            );

            if (empty($palet->id_produksi_sanding) && $idProduksiSanding) {
                $palet->id_produksi_sanding = $idProduksiSanding;
                $palet->save();
            }

            $selisihModalMasuk  = $v['modal'] - $v['masuk'];
            $selisihMasukKeluar = $v['masuk'] - $v['keluar'];
            $alasanKeterangan   = $v['alasan'] ?? 'normal';

            // Simpan audit putaran (Face / Back)
            $palet->putaran()->updateOrCreate(
                [
                    'sisi'       => $v['sisi'],
                    'putaran_ke' => $v['putaran_ke']
                ],
                [
                    'modal'       => $v['modal'],
                    'hasil'       => $v['keluar'],
                    'selisih'     => $selisihMasukKeluar,
                    'status_cek'  => ($selisihMasukKeluar == 0 && $alasanKeterangan == 'normal') ? 'sesuai' : 'selisih',
                    'catatan'     => "Alasan: [{$alasanKeterangan}] | [Modal-Masuk: {$selisihModalMasuk}] [Masuk-Keluar: {$selisihMasukKeluar}]",
                    'waktu_mesin' => now(),
                ]
            );

            // Hitung status kelengkapan sisi
            $face = $palet->putaran()->where('sisi', 'face')->count();
            $back = $palet->putaran()->where('sisi', 'back')->count();

            $palet->jumlah_sanding_face = $face;
            $palet->jumlah_sanding_back = $back;

            $selesai = ($face >= 1 && $back >= 1);
            $palet->status = $selesai ? 'Selesai 2 Sisi' : 'Selesai 1 Sisi';
            $palet->save();

            return response()->json([
                'status'  => 'success',
                'message' => 'Data putaran sanding berhasil diproses secara otomatis',
                'face'    => $face,
                'back'    => $back,
                'selesai' => $selesai,
            ], 201);
        });
    }

    /**
     * 3. POST: Menutup sesi sanding atas keputusan operator (manual).
     */
    public function selesai(SerahTerimaHp $serahTerimaHp)
    {
        return DB::transaction(function () use ($serahTerimaHp) {
            $palet = HasilSanding::where(
                'id_serah_terima_hp',
                $serahTerimaHp->id
            )->lockForUpdate()->first();

            if (!$palet) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Data hasil sanding belum ditemukan.',
                ], 404);
            }

            $face = $palet->putaran()
                ->where('sisi', 'face')
                ->count();

            $back = $palet->putaran()
                ->where('sisi', 'back')
                ->count();

            $palet->jumlah_sanding_face = $face;
            $palet->jumlah_sanding_back = $back;

            if ($face > 0 && $back > 0) {
                $palet->status = 'Selesai 2 Sisi';
            } elseif ($face > 0 || $back > 0) {
                $palet->status = 'Selesai 1 Sisi';
            } else {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sesi tidak dapat diselesaikan karena belum ada putaran FACE atau BACK.',
                ], 422);
            }

            $palet->save();

            return response()->json([
                'status'              => 'success',
                'message'             => 'Sesi sanding berhasil ditutup.',
                'jumlah_sanding_face' => $face,
                'jumlah_sanding_back' => $back,
                'status_sanding'      => $palet->status,
                'selesai'             => true,
            ]);
        });
    }

    /**
     * 4. GET: Mengambil riwayat sanding yang sudah selesai.
     */
    public function riwayatSelesai(Request $request)
    {
        $tipeSanding   = $request->query('tipe', 'besar');
        $tanggalFilter = $request->query('tanggal', date('Y-m-d'));
        $namaMesin     = 'Sanding ' . ucfirst($tipeSanding);

        $query = SerahTerimaHp::with([
            'hasilSanding.putaran',
        ])
            ->where('tujuan', 'sanding')
            ->where('diterima_oleh', '!=', '-')
            ->whereNull('ditolak_oleh')
            ->whereDate('created_at', $tanggalFilter);

        $data = $query->orderBy('id', 'desc')
            ->get()
            ->map(function ($item) use ($namaMesin) {
                $hasil = $item->hasilSanding;

                if (!$hasil) {
                    return null;
                }

                $faceCount = $hasil->putaran()
                    ->where('sisi', 'face')
                    ->count();

                $backCount = $hasil->putaran()
                    ->where('sisi', 'back')
                    ->count();

                $sudahSelesai =
                    in_array($hasil->status, [
                        'Selesai 1 Sisi',
                        'Selesai 2 Sisi',
                        'Selesai Manual',
                    ], true)
                    || ($faceCount >= 1 && $backCount >= 1);

                if (!$sudahSelesai) {
                    return null;
                }

                $timestamp = $item->created_at ?? now();
                $jam       = $timestamp->format('H:i:s');
                $shift     = ($jam >= '06:00:00' && $jam < '16:00:00') ? 'PAGI' : 'MALAM';

                $tanggalProduksi = $timestamp->format('Y-m-d');
                if ($jam >= '00:00:00' && $jam < '06:00:00') {
                    $tanggalProduksi = $timestamp
                        ->copy()
                        ->subDay()
                        ->format('Y-m-d');
                }

                return [
                    'id'                  => $item->id,
                    'id_hasil_sanding'    => $hasil->id,
                    'id_produksi_sanding' => $hasil->id_produksi_sanding,
                    'tanggal'             => $tanggalProduksi,
                    'mesin'               => $namaMesin,
                    'shift'               => $shift,
                    'no_palet'            => $hasil->no_palet,
                    'nama_barang'         => null,
                    'target'              => $item->sisa ?? 0,
                    'jumlah_sanding_face' => $faceCount,
                    'jumlah_sanding_back' => $backCount,
                    'status'              => $hasil->status,
                    'sudah_selesai'       => true,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'status'  => 'success',
            'tipe'    => $tipeSanding,
            'tanggal' => $tanggalFilter,
            'jumlah'  => $data->count(),
            'data'    => $data,
        ]);
    }
}
