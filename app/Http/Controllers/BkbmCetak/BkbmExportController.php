<?php

namespace App\Http\Controllers\BkbmCetak;

use App\Exports\BkbmSheetExport;
use App\Http\Controllers\Controller;
use App\Models\Bk;
use App\Models\Bm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Export Excel menu BK/BM baru (sederhana, tanpa nomor akun).
 *  - Rekap BK / Rekap BM: semua nota yang sudah divalidasi dalam rentang tanggal, satu baris per barang.
 *  - Barang belum kembali: BKL dan BKK yang masih di luar, yang paling lama di atas.
 */
class BkbmExportController extends Controller
{
    /** Lama di luar (hari) yang diberi warna merah di export "Barang belum kembali". Ubah angka ini bila perlu. */
    public const BATAS_MERAH_HARI = 30;

    private function bisaLihatHarga(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['edmeros', 'super_admin', 'Super Admin']);
    }

    private function rentang(Request $r): array
    {
        $dari = $r->filled('dari') ? Carbon::parse($r->input('dari'))->startOfDay() : now()->startOfMonth();
        $sampai = $r->filled('sampai') ? Carbon::parse($r->input('sampai'))->endOfDay() : now()->endOfDay();
        return [$dari, $sampai];
    }

    private function jenis(Request $r): array
    {
        return array_values(array_filter((array) $r->input('jenis', [])));
    }

    private static function tgl($t): string
    {
        return $t ? Carbon::parse($t)->format('d/m/Y') : '';
    }

    public function rekapBk(Request $r)
    {
        [$dari, $sampai] = $this->rentang($r);
        $harga = $this->bisaLihatHarga();

        $notas = Bk::with('detail')->where('status', '!=', 'draft')
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->when($this->jenis($r), fn ($q, $j) => $q->whereIn('kategori', $j))
            ->orderBy('tanggal')->orderBy('id')->get();

        $rows = [];
        foreach ($notas as $n) {
            foreach ($n->detail as $d) {
                $baris = [self::tgl($n->tanggal), $n->no_nota, strtoupper($n->kategori), $n->tujuan_nota, $d->nama_barang, (float) $d->jumlah, $d->satuan, $d->keterangan];
                if ($harga) {
                    $baris[] = $d->harga ? (float) $d->harga : null;
                }
                $rows[] = $baris;
            }
        }
        $head = ['Tanggal', 'No. Nota', 'Jenis', 'Kepada', 'Nama Barang', 'Banyak', 'Satuan', 'Keterangan'];
        if ($harga) {
            $head[] = 'Harga';
        }

        return Excel::download(new BkbmSheetExport($rows, $head), 'rekap-bk-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.xlsx');
    }

    public function rekapBm(Request $r)
    {
        [$dari, $sampai] = $this->rentang($r);

        $notas = Bm::with('detail')->where('status', '!=', 'draft')
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->when($this->jenis($r), fn ($q, $j) => $q->whereIn('kategori', $j))
            ->orderBy('tanggal')->orderBy('id')->get();

        $rows = [];
        foreach ($notas as $n) {
            foreach ($n->detail as $d) {
                $rows[] = [self::tgl($n->tanggal), $n->no_nota, strtoupper($n->kategori), $n->dari, $d->nama_barang, (float) $d->jumlah, $d->satuan, $d->keterangan];
            }
        }
        $head = ['Tanggal', 'No. Nota', 'Jenis', 'Dari', 'Nama Barang', 'Banyak', 'Satuan', 'Keterangan'];

        return Excel::download(new BkbmSheetExport($rows, $head), 'rekap-bm-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.xlsx');
    }

    public function belumKembali(Request $r)
    {
        $jenis = array_values(array_intersect($this->jenis($r), ['bkl', 'bkk'])) ?: ['bkl', 'bkk'];

        $notas = Bk::with('detail')->whereIn('kategori', $jenis)->whereIn('status', ['divalidasi', 'dipinjam'])
            ->when($r->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', Carbon::parse($r->input('dari'))->toDateString()))
            ->when($r->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', Carbon::parse($r->input('sampai'))->toDateString()))
            ->orderBy('divalidasi_at')->orderBy('id')->get();

        $rows = [];
        $merah = [];
        foreach ($notas as $n) {
            $hari = $n->divalidasi_at ? (int) $n->divalidasi_at->diffInDays(now()) : null;
            foreach ($n->detail as $d) {
                if ($hari !== null && $hari > self::BATAS_MERAH_HARI) {
                    $merah[] = count($rows);
                }
                $rows[] = [self::tgl($n->tanggal), $n->no_nota, strtoupper($n->kategori), $n->tujuan_nota, $d->nama_barang, (float) $d->jumlah, $d->satuan, $d->keterangan,
                    $n->labelStatus(), $hari];
            }
        }
        $head = ['Tanggal Keluar', 'No. Nota', 'Jenis', 'Kepada', 'Nama Barang', 'Banyak', 'Satuan', 'Keterangan', 'Status', 'Lama di Luar (hari)'];

        return Excel::download(new BkbmSheetExport($rows, $head, $merah), 'barang-belum-kembali-' . now()->format('Ymd') . '.xlsx');
    }
}