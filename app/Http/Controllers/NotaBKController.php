<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKayu;
use App\Models\DetailNotaBarangKeluar;
use App\Models\NotaBarangKeluar;
use Excel;
use Illuminate\Support\Facades\DB;

use App\Models\RekeningPerusahaan;
use Illuminate\Http\Request;

class NotaBKController extends Controller
{
    public function show(NotaBarangKeluar $record)
    {
        // Muat relasi yang diperlukan


        return view('nota-barang.bk-print', [
            'record' => $record,
            'details' => $record->detail,
        ]);
    }

    /**
     * Cetak Nota Barang Keluar (Format Nota F4 / Plywood & BSJ HP).
     */
    public function printBarangKeluar(NotaBarangKeluar $record)
    {
        $record->load([
            'pembuat',
            'validator',
            'plywoodMutasi.details.ukuran',
            'plywoodMutasi.details.jenisKayu',
            'detail',
        ]);

        $items = ($record->plywoodMutasi && $record->plywoodMutasi->details->isNotEmpty())
            ? $record->plywoodMutasi->details
            : $record->detail;

        return view('nota.barang-keluar', [
            'notaBarangKeluar' => $record,
            'items' => $items,
        ]);
    }

    /**
     * Helper resolve items dan penamaan untuk nota kantor dan nota sales.
     */
    protected function resolveNotaItems(NotaBarangKeluar $record, string $jenisNota = 'kantor'): array
    {
        $record->load([
            'pembuat',
            'validator',
            'rekeningPerusahaan',
            'plywoodMutasi.details.ukuran',
            'plywoodMutasi.details.jenisKayu',
            'detail',
        ]);

        $rawItems = ($record->plywoodMutasi && $record->plywoodMutasi->details->isNotEmpty())
            ? $record->plywoodMutasi->details
            : $record->detail;

        $items = [];
        $grandTotal = 0;

        foreach ($rawItems as $detail) {
            $satuan = $detail->satuan ?? 'lembar';
            $qty = (float) ($detail->qty ?? ($detail->jumlah ?? 0));
            $harga = (float) ($detail->harga ?? 0);
            $subtotal = $qty * $harga;
            $grandTotal += $subtotal;

            // Helper untuk mengambil data tebal secara dinamis
            $tebalVal = null;
            if (isset($detail->ukuran) && filled($detail->ukuran?->tebal)) {
                $tebalVal = $detail->ukuran->tebal;
            } elseif ($detail->barang?->ukuran && filled($detail->barang->ukuran->tebal)) {
                $tebalVal = $detail->barang->ukuran->tebal;
            } elseif (! empty($detail->id_ukuran)) {
                $ukuranObj = \App\Models\Ukuran::find($detail->id_ukuran);
                if ($ukuranObj && filled($ukuranObj->tebal)) {
                    $tebalVal = $ukuranObj->tebal;
                }
            } elseif (! empty($detail->nama_barang)) {
                if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:mm|m)?\b/i', $detail->nama_barang, $matches)) {
                    $tebalVal = str_replace(',', '.', $matches[1]);
                }
            }

            $tebalStr = null;
            if ($tebalVal !== null && $tebalVal !== '') {
                $tebalFormatted = (float) $tebalVal == (int) $tebalVal
                    ? (int) $tebalVal
                    : rtrim(rtrim((string) $tebalVal, '0'), '.');
                $tebalStr = "{$tebalFormatted} mm";
            }

            if ($jenisNota === 'sales') {
                // NOTA SALES: [tebal] [merek]
                // 1. Dapatkan merek (fallback 'Plywood' jika null atau kosong)
                $rawMerek = $detail->barang?->merek ?? null;
                $merek = (! empty(trim($rawMerek ?? ''))) ? trim($rawMerek) : 'Plywood';

                // 2. Susun format: [tebal] [merek]
                if ($tebalStr !== null) {
                    $namaBarang = "{$tebalStr} {$merek}";
                } else {
                    $namaBarang = $merek;
                }
            } else {
                // NOTA KANTOR: [tebal] [grade]
                // 1. Dapatkan grade dari BarangSetengahJadiHp -> relasi grade (id_grade)
                $bshp = $detail->barang ?? null;
                $gradeModel = $bshp?->grade ?? ($bshp?->id_grade ? \App\Models\Grade::find($bshp->id_grade) : null);
                $gradeName = ! empty(trim($gradeModel?->nama_grade ?? '')) ? trim($gradeModel->nama_grade) : null;

                // Fallback grade jika detail mutasi memiliki kw_grade dan bshp belum ter-resolve
                if (! $gradeName && ! empty(trim($detail->kw_grade ?? ''))) {
                    $gradeName = trim($detail->kw_grade);
                }

                // 2. Susun format:
                // Jika thickness dan grade tersedia: 12 mm A
                // Jika thickness tersedia tetapi grade tidak: 12 mm
                // Jika thickness tidak ada tetapi grade ada: A
                // Jika keduanya tidak ada: fallback label/nama_barang asli
                if ($tebalStr !== null && $gradeName !== null) {
                    $namaBarang = "{$tebalStr} {$gradeName}";
                } elseif ($tebalStr !== null) {
                    $namaBarang = $tebalStr;
                } elseif ($gradeName !== null) {
                    $namaBarang = $gradeName;
                } else {
                    $namaBarang = $detail->barang?->label ?? ($detail->nama_barang ?? '-');
                }
            }

            $keterangan = $detail->keterangan ?? '';

            $items[] = (object) [
                'nama_barang' => $namaBarang,
                'satuan'      => $satuan,
                'qty'         => $qty,
                'harga'       => $harga,
                'potongan'    => 0,
                'subtotal'    => $subtotal,
                'keterangan'  => $keterangan,
            ];
        }

        return [$items, $grandTotal];
    }

    /**
     * Halaman Preview Nota Kantor / Sales dengan pemilihan Metode Pembayaran.
     */
    public function preview(NotaBarangKeluar $record, string $jenis)
    {
        $jenis = strtolower($jenis) === 'sales' ? 'sales' : 'kantor';
        [$items, $grandTotal] = $this->resolveNotaItems($record, $jenis);
        $rekeningList = RekeningPerusahaan::all();

        return view('nota.preview', [
            'record'        => $record,
            'jenis'         => $jenis,
            'items'         => $items,
            'grandTotal'    => $grandTotal,
            'rekeningList'  => $rekeningList,
        ]);
    }

    public function savePayment(Request $request, NotaBarangKeluar $record)
    {
        $validated = $request->validate([
            'metode_pembayaran'      => 'required|string|in:Tunai,Transfer,Cek / Giro',
            'id_rekening_perusahaan' => 'nullable|integer|exists:rekening_perusahaan,id',
            'jenis'                  => 'required|string|in:kantor,sales',
            'cetak_action'           => 'required|string|in:nota,sj,semua',
        ]);

        $record->update([
            'metode_pembayaran'      => $validated['metode_pembayaran'],
            'id_rekening_perusahaan' => $validated['metode_pembayaran'] === 'Transfer'
                ? $validated['id_rekening_perusahaan']
                : null,
        ]);

        if ($validated['cetak_action'] === 'nota') {
            $targetRoute = $validated['jenis'] === 'sales' ? 'nota-bk.nota-sales' : 'nota-bk.nota-kantor';
            return redirect()->route($targetRoute, $record);
        } elseif ($validated['cetak_action'] === 'sj') {
            return redirect()->route('surat-jalan.bk', ['nota' => $record->id, 'jenis' => $validated['jenis']]);
        } else {
            return redirect()->route('nota-bk.cetak-semua', ['record' => $record->id, 'jenis' => $validated['jenis']]);
        }
    }

    public function cetakSemua(NotaBarangKeluar $record, $jenis)
    {
        [$items, $grandTotal] = $this->resolveNotaItems($record, $jenis);

        $record->load(['detail', 'pembuat', 'plywoodMutasi.details.ukuran', 'plywoodMutasi.details.jenisKayu']);
        $sjDetails = $record->detail->map(function ($d) use ($record) {
            if (str_starts_with($d->nama_barang, 'Plywood ')) {
                $matchedDetail = null;
                if ($record->plywoodMutasi) {
                    foreach ($record->plywoodMutasi->details as $mutasiDetail) {
                        $ukuran = $mutasiDetail->ukuran;
                        $jenisKayu = $mutasiDetail->jenisKayu;
                        if (!$ukuran || !$jenisKayu) continue;

                        $expectedName = 'Plywood - '.$ukuran->nama_ukuran
                            .' - '.$jenisKayu->nama_kayu
                            .' - KW '.$mutasiDetail->kw_grade;

                        if ($expectedName === $d->nama_barang && (int) $mutasiDetail->qty === (int) $d->jumlah) {
                            $matchedDetail = $mutasiDetail;
                            break;
                        }
                    }
                }

                $tebalVal = null;
                if ($matchedDetail && $matchedDetail->ukuran && filled($matchedDetail->ukuran->tebal)) {
                    $tebalVal = $matchedDetail->ukuran->tebal;
                } elseif (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:mm|m)?\b/i', $d->nama_barang, $matches)) {
                    $tebalVal = str_replace(',', '.', $matches[1]);
                }

                $tebalStr = null;
                if ($tebalVal !== null && $tebalVal !== '') {
                    $tebalFormatted = (float) $tebalVal == (int) $tebalVal
                        ? (int) $tebalVal
                        : rtrim(rtrim((string) $tebalVal, '0'), '.');
                    $tebalStr = "{$tebalFormatted} mm";
                }

                $rawMerek = $matchedDetail ? ($matchedDetail->barang?->merek ?? null) : null;
                $merek = (! empty(trim($rawMerek ?? ''))) ? trim($rawMerek) : 'Plywood';

                if ($tebalStr !== null) {
                    $d->nama_barang = "{$tebalStr} {$merek}";
                } else {
                    $d->nama_barang = $merek;
                }
            }
            return $d;
        });

        return view('nota.cetak-semua', [
            'record'     => $record,
            'nota'       => $record,
            'jenis'      => $jenis,
            'items'      => $items,
            'grandTotal' => $grandTotal,
            'details'    => $sjDetails,
        ]);
    }

    /**
     * Cetak Nota Kantor (Layout Gambar 1).
     */
    public function printNotaKantor(NotaBarangKeluar $record)
    {
        [$items, $grandTotal] = $this->resolveNotaItems($record, 'kantor');

        return view('nota.nota-kantor', [
            'record'     => $record,
            'items'      => $items,
            'grandTotal' => $grandTotal,
        ]);
    }

    /**
     * Cetak Nota Sales (Layout Gambar 2).
     */
    public function printNotaSales(NotaBarangKeluar $record)
    {
        [$items, $grandTotal] = $this->resolveNotaItems($record, 'sales');

        return view('nota.nota-sales', [
            'record'     => $record,
            'items'      => $items,
            'grandTotal' => $grandTotal,
        ]);
    }

    // ✅ REKAP NOTA MASUK
    public function rekap()
    {
        $details = DetailNotaBarangKeluar::query()
            ->join('nota_barang_keluar as n', 'n.id', '=', 'detail_nota_barang_keluar.id_nota_bk')

            ->leftJoin('users as u1', 'u1.id', '=', 'n.dibuat_oleh')
            ->leftJoin('users as u2', 'u2.id', '=', 'n.divalidasi_oleh')

            ->whereNotNull('n.divalidasi_oleh')

            ->orderByDesc('n.tanggal')   // tanggal nota terbaru
            ->orderByDesc('n.id')
            ->orderByDesc('detail_nota_barang_keluar.id')

            ->select([
                'n.tanggal',
                'n.no_nota',
                'n.tujuan_nota',
                DB::raw('u1.name as dibuat_oleh'),
                DB::raw('u2.name as divalidasi_oleh'),
                'detail_nota_barang_keluar.nama_barang',
                'detail_nota_barang_keluar.jumlah',
                'detail_nota_barang_keluar.satuan',
                'detail_nota_barang_keluar.keterangan',
            ])
            ->get();

        return view('nota-barang.bk-rekap', [
            'details' => $details,
        ]);
    }

    public function exportExcel()
    {
        $query = DB::table('detail_nota_barang_keluar as d')
            ->join('nota_barang_keluar as n', 'n.id', '=', 'd.id_nota_bk')

            ->leftJoin('users as u1', 'u1.id', '=', 'n.dibuat_oleh')
            ->leftJoin('users as u2', 'u2.id', '=', 'n.divalidasi_oleh')

            ->whereNotNull('n.divalidasi_oleh')

            ->orderByDesc('n.tanggal')
            ->orderByDesc('n.id')
            ->orderByDesc('d.id')

            ->select([
                'n.tanggal',
                'n.no_nota',
                'n.tujuan_nota',
                DB::raw('u1.name as dibuat_oleh'),
                DB::raw('u2.name as divalidasi_oleh'),
                'd.nama_barang',
                'd.jumlah',
                'd.satuan',
                'd.keterangan',
            ]);

        $columns = [
            ['field' => 'tanggal', 'label' => 'Tanggal'],
            ['field' => 'no_nota', 'label' => 'No Nota'],
            ['field' => 'tujuan_nota', 'label' => 'Tujuan Nota'],
            ['field' => 'dibuat_oleh', 'label' => 'Dibuat Oleh'],
            ['field' => 'divalidasi_oleh', 'label' => 'Divalidasi Oleh'],
            ['field' => 'nama_barang', 'label' => 'Nama Barang'],
            ['field' => 'jumlah', 'label' => 'Jumlah'],
            ['field' => 'satuan', 'label' => 'Satuan'],
            ['field' => 'keterangan', 'label' => 'Keterangan'],
        ];

        return Excel::download(
            new LaporanKayu($query, $columns),
            'rekap-nota-barang-keluar.xlsx'
        );
    }

}