<?php

namespace App\Http\Controllers;

use App\Models\ReferensiHargaProduksi;
use App\Models\Ukuran;
use App\Models\JenisKayu;
use App\Http\Requests\ReferensiHargaProduksiRequest;
use Illuminate\Http\Request;

class ReferensiHargaProduksiController extends Controller
{
    /**
     * Tampilkan List Data
     */
    public function index(Request $request)
    {
        $query = ReferensiHargaProduksi::with(['ukuran', 'jenisKayu', 'subAnakAkun']);

        // Fitur Pencarian (Search)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('jenis_barang', 'like', "%{$search}%")
                    ->orWhere('kw', 'like', "%{$search}%")
                    ->orWhere('harga', 'like', "%{$search}%")
                    ->orWhereHas('ukuran', function ($qu) use ($search) {
                        $qu->where('panjang', 'like', "%{$search}%")
                            ->orWhere('lebar', 'like', "%{$search}%")
                            ->orWhere('tebal', 'like', "%{$search}%");
                    })
                    ->orWhereHas('jenisKayu', function ($qk) use ($search) {
                        $qk->where('nama_kayu', 'like', "%{$search}%")
                            ->orWhere('kode_kayu', 'like', "%{$search}%");
                    })
                    ->orWhereHas('subAnakAkun', function ($qsa) use ($search) {
                        $qsa->where('nama_sub_anak_akun', 'like', "%{$search}%")
                            ->orWhere('kode_sub_anak_akun', 'like', "%{$search}%");
                    });
            });
        }

        // Fitur Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');

        if (in_array($sortField, ['nama', 'jenis_barang', 'kw', 'harga', 'created_at'])) {
            $query->orderBy($sortField, $sortOrder);
        } elseif ($sortField === 'ukuran') {
            $query->join('ukurans', 'referensi_harga_produksi.id_ukuran', '=', 'ukurans.id')
                ->select('referensi_harga_produksi.*')
                ->orderBy('ukurans.panjang', $sortOrder)
                ->orderBy('ukurans.lebar', $sortOrder)
                ->orderBy('ukurans.tebal', $sortOrder);
        } elseif ($sortField === 'jenis_kayu') {
            $query->join('jenis_kayus', 'referensi_harga_produksi.id_jenis_kayu', '=', 'jenis_kayus.id')
                ->select('referensi_harga_produksi.*')
                ->orderBy('jenis_kayus.nama_kayu', $sortOrder);
        } elseif ($sortField === 'sub_anak_akun') {
            $query->leftJoin('sub_anak_akuns', 'referensi_harga_produksi.id_sub_anak_akun', '=', 'sub_anak_akuns.id')
                ->select('referensi_harga_produksi.*')
                ->orderBy('sub_anak_akuns.nama_sub_anak_akun', $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Fitur Pagination
        $data = $query->paginate(100)->withQueryString();

        return view('referensi_harga_produksi.index', compact('data'));
    }

    public function create()
    {
        $ukurans = Ukuran::all();
        $jenisKayus = JenisKayu::all();
        $subAnakAkuns = \App\Models\SubAnakAkun::all();
        $standardJenisBarangs = collect([
            'Afalan',
            'Veneer Basah',
            'Veneer Kering',
            'Veneer Jadi',
            'Veneer',
            'Platform',
            'Plywood',
            'Barang',
            'Lain-Lain'
        ]);
        $dbJenisBarangs = ReferensiHargaProduksi::whereNotNull('jenis_barang')->where('jenis_barang', '!=', '')->distinct()->pluck('jenis_barang');
        $jenisBarangs = $standardJenisBarangs->merge($dbJenisBarangs)->unique()->values();
        $kws = ReferensiHargaProduksi::whereNotNull('kw')->where('kw', '!=', '')->distinct()->pluck('kw')->filter()->values();

        return view('referensi_harga_produksi.create', compact('ukurans', 'jenisKayus', 'subAnakAkuns', 'jenisBarangs', 'kws'));
    }

    /**
     * Simpan Data Baru (Store)
     */
    public function store(ReferensiHargaProduksiRequest $request)
    {
        ReferensiHargaProduksi::create($request->validated());

        return redirect()->route('referensi-harga-produksi.index')
            ->with('success', 'Referensi Harga Produksi berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $referensiHargaProduksi = ReferensiHargaProduksi::findOrFail($id);
        $ukurans = Ukuran::all();
        $jenisKayus = JenisKayu::all();
        $subAnakAkuns = \App\Models\SubAnakAkun::all();
        $standardJenisBarangs = collect([
            'Afalan',
            'Veneer Basah',
            'Veneer Kering',
            'Veneer Jadi',
            'Veneer',
            'Platform',
            'Plywood',
            'Barang',
            'Lain-Lain'
        ]);
        $dbJenisBarangs = ReferensiHargaProduksi::whereNotNull('jenis_barang')->where('jenis_barang', '!=', '')->distinct()->pluck('jenis_barang');
        $jenisBarangs = $standardJenisBarangs->merge($dbJenisBarangs)->unique()->values();
        $kws = ReferensiHargaProduksi::whereNotNull('kw')->where('kw', '!=', '')->distinct()->pluck('kw')->filter()->values();

        return view('referensi_harga_produksi.edit', compact('referensiHargaProduksi', 'ukurans', 'jenisKayus', 'subAnakAkuns', 'jenisBarangs', 'kws'));
    }

    /**
     * Update Data (Update)
     */
    public function update(ReferensiHargaProduksiRequest $request, $id)
    {
        $referensiHargaProduksi = ReferensiHargaProduksi::findOrFail($id);
        $referensiHargaProduksi->update($request->validated());

        return redirect()->route('referensi-harga-produksi.index')
            ->with('success', 'Referensi Harga Produksi berhasil diperbarui.');
    }

    /**
     * Hapus Data (Delete)
     */
    public function destroy($id)
    {
        $referensiHargaProduksi = ReferensiHargaProduksi::findOrFail($id);
        $referensiHargaProduksi->delete();

        return redirect()->route('referensi-harga-produksi.index')
            ->with('success', 'Referensi Harga Produksi berhasil dihapus.');
    }
}
