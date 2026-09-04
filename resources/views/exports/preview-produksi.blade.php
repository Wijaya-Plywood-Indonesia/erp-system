<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Persentase Kayu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print {
                display: none;
            }

            body {
                background: white;
                padding: 0;
            }

            .p-6 {
                padding: 0;
            }
        }

        @keyframes loading-spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .loading-spinner {
            animation: loading-spin 0.8s linear infinite;
        }
    </style>
</head>

<body class="antialiased text-slate-800 font-bold">

    <div class="p-6">
        <div class="mb-6 bg-white p-4 rounded-lg border border-slate-900 shadow-sm no-print">
            <form id="filter-form" action="{{ url()->current() }}" method="GET">
                {{-- Simpan sheet aktif supaya tetap terpilih saat bulan/tahun diganti --}}
                <input type="hidden" name="sheet" id="filter-sheet" value="{{ $activeSheet }}">

                <div class="flex items-center justify-between  gap-4">
                    <div class="flex items-end gap-4">

                        <div>
                            <label class="block text-xs font-black text-slate-700 uppercase mb-1">Bulan Produksi</label>
                            <select name="bulan" id="filter-bulan"
                                class="border border-slate-900 rounded px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                                @foreach (range(1, 12) as $m)
                                    <option value="{{ sprintf('%02d', $m) }}"
                                        {{ $selectedBulan == $m ? 'selected' : '' }}>
                                        {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-700 uppercase mb-1">Tahun</label>
                            <select name="tahun" id="filter-tahun"
                                class="border border-slate-900 rounded px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                                @foreach (range(date('Y') - 2, date('Y')) as $y)
                                    <option value="{{ $y }}" {{ $selectedTahun == $y ? 'selected' : '' }}>
                                        {{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Indikator loading kecil di samping dropdown, muncul saat data sedang dimuat ulang --}}
                        <div id="filter-inline-loading"
                            class="hidden items-center gap-2 text-xs font-black text-slate-600 uppercase pb-2">
                            <svg class="loading-spinner h-4 w-4 text-slate-600" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-90" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Memuat...
                        </div>
                    </div>

                    {{--
                        TOMBOL EXPORT — SATU ELEMEN SAJA (bukan @if/@else dua tombol berbeda).
                        Statusnya (aktif / kosong) sepenuhnya dikendalikan oleh JS lewat class
                        & atribut data-*, berdasarkan flag `data-sheets-empty` yang dikirim server
                        di dalam fragment tabel setiap kali difetch ulang (ganti bulan/tahun/sheet).
                        Nilai awal saat render pertama tetap dihitung dari $isSheetsEmpty server-side
                        supaya tidak "flash" sebelum JS jalan.
                    --}}
                    @php
                        $isSheetsEmpty = empty($sheets) || count($sheets) === 0;
                    @endphp
                    <a id="export-excel-btn"
                        href="{{ $isSheetsEmpty ? '#' : route('produksi.export-excel', request()->query()) }}"
                        target="_blank" data-empty="{{ $isSheetsEmpty ? '1' : '0' }}"
                        data-export-url-base="{{ route('produksi.export-excel') }}"
                        class="inline-flex items-center px-4 py-2 text-white text-sm font-bold rounded-lg shadow-sm transition-all
                        {{ $isSheetsEmpty ? 'bg-slate-400 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700' }}">

                        <svg class="export-icon w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <svg class="export-spinner loading-spinner w-4 h-4 mr-2 hidden" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-90" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span
                            class="export-label">{{ $isSheetsEmpty ? 'EXPORT EXCEL (KOSONG)' : 'EXPORT EXCEL' }}</span>
                    </a>
                </div>
            </form>
        </div>

        <div id="table-wrapper" class="relative overflow-x-auto rounded-lg shadow-sm border border-slate-900">
            {{-- LOADING OVERLAY LOKAL: hanya menutupi tabel, bukan seluruh halaman.
                 Dropdown bulan/tahun di luar wrapper ini tetap bisa diklik & diubah
                 selagi tabel masih memuat data periode sebelumnya.
                 Ditampilkan default (tanpa 'hidden') supaya SAAT FIRST LOAD pun
                 area tabel langsung terlihat sedang "memuat", bukan tabel kosong. --}}
            <div id="table-loading-overlay"
                class="flex absolute inset-0 z-40 bg-white/80 backdrop-blur-sm flex-col items-center justify-center no-print">
                <svg class="loading-spinner h-10 w-10 text-slate-700 mb-3" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4">
                    </circle>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                    </path>
                </svg>
                <p class="text-slate-800 font-black text-sm uppercase tracking-wide">Memuat Data...</p>
            </div>

            {{-- Semua isi <table> ada di dalam fragment ini. Controller bisa
                 me-render ULANG HANYA bagian ini lewat
                 view(...)->fragment('table-content') tanpa perlu render ulang
                 form filter / tab sheet di atas. Dipakai baik untuk request
                 AJAX first-load maupun ganti filter/sheet.

                 PENTING: fragment ini juga membawa `data-sheets-empty` &
                 `data-sheets-json` di wrapper terluarnya, supaya JS bisa baca
                 status sheets HASIL FETCH TERBARU dan sinkronkan tombol
                 export tanpa perlu endpoint/partial terpisah. --}}
            <div id="table-fragment-container">
                @fragment('table-content')
                    @php
                        // Saat FIRST LOAD (non-AJAX), controller sengaja mengirim
                        // $rekap = null dan $laporan = collect() kosong supaya query
                        // berat tidak dijalankan di request pertama. Guard di sini
                        // supaya blade tidak error sebelum data asli datang via fetch().
                        $rekap = $rekap ?? [];
                        $fragmentSheetsEmpty = empty($sheets) || count($sheets) === 0;

                        // FIX: sama seperti di halaman index (Livewire) & Excel export,
                        // kolom "Solasi" dan "Biaya Bahan Penolong" hanya ditampilkan
                        // kalau MINIMAL SATU baris laporan punya bahan penolong.
                        // Kolom "Harga VOP + Bahan Penolong" DIHAPUS dari preview ini
                        // (konsisten dengan export Excel).
                        $adaBahanPenolong = collect($laporan)->contains(
                            fn($item) => ($item['summary']['total_bahan_penolong'] ?? 0) > 0,
                        );

                        // Total kolom pada header baris judul lahan (colspan),
                        // menyesuaikan jumlah kolom aktual di tabel. +1 selalu
                        // untuk kolom "Harga Total / m³" yang selalu ada, sama
                        // seperti di export Excel.
                        // 20 kolom dasar + 2 kolom bahan penolong (Solasi, Biaya
                        // Bahan Penolong) kalau adaBahanPenolong + 1 kolom Harga
                        // Total/m³ (selalu ada).
                        $totalKolom = $adaBahanPenolong ? 23 : 21;

                        // Nilai baris Total untuk kolom "Harga Total / m³".
                        //
                        // FIX DOUBLE DIVISION: $item['summary']['harga_vop'] /
                        // harga_vopb SUDAH MERUPAKAN RATE PER M³ (dihitung di
                        // PreviewPersentaseKayu::normalizeLaporanItem() sebagai
                        // (poin + ongkos + penyusutan) / outflowM3), BUKAN
                        // nominal total. Karena itu, baris Total TIDAK BOLEH
                        // menghitung "total_harga_vop (dari $rekap) dibagi
// total_kubikasi_veneer" lagi — itu akan membagi rate
                        // dengan kubikasi untuk KEDUA KALINYA.
                        //
                        // Sebagai gantinya, "Harga Total / m³" pada baris Total
                        // dihitung sebagai RATA-RATA TERTIMBANG langsung dari
                        // rate per batch ($laporan), ditimbang dengan kubikasi
                        // keluar tiap batch:
                        //
                        //     sum(rate_batch * kubikasi_batch) / sum(kubikasi_batch)
                        //
                        // Ini konsisten dengan fix yang sama di
                        // ExportExcelPersentaseKayuService, dan tidak lagi
                        // bergantung pada makna $rekap['total_harga_vop'] /
                        // $rekap['total_harga_vopb'] yang ambigu.
                        $sumRateKaliKubikasiSemua = 0.0;
                        $sumKubikasiSemuaBatch = 0.0;

                        foreach ($laporan as $itemUntukTotal) {
                            $adaBahanBatchIniUntukTotal = ($itemUntukTotal['summary']['total_bahan_penolong'] ?? 0) > 0;
                            $rateBatchIniUntukTotal = $adaBahanBatchIniUntukTotal
                                ? (float) ($itemUntukTotal['summary']['harga_vopb'] ?? 0)
                                : (float) ($itemUntukTotal['summary']['harga_vop'] ?? 0);
                            $kubikasiBatchIniUntukTotal = (float) ($itemUntukTotal['summary']['total_keluar_m3'] ?? 0);

                            $sumRateKaliKubikasiSemua += $rateBatchIniUntukTotal * $kubikasiBatchIniUntukTotal;
                            $sumKubikasiSemuaBatch += $kubikasiBatchIniUntukTotal;
                        }

                        $totalHargaPerM3Semua =
                            $sumKubikasiSemuaBatch > 0 ? $sumRateKaliKubikasiSemua / $sumKubikasiSemuaBatch : 0;
                    @endphp
                    <div data-sheets-empty="{{ $fragmentSheetsEmpty ? '1' : '0' }}"
                        data-export-query="{{ http_build_query(request()->query()) }}">
                        <table class="w-full border-collapse bg-white text-sm font-sans">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-900 text-slate-900 uppercase">
                                    <th colspan="{{ $totalKolom }}" class="py-4 text-center font-black text-lg">
                                        KAYU {{ $activeSheet ?? 'KOSONG' }}
                                    </th>
                                </tr>
                                <tr class="bg-slate-100/80 border-b border-slate-900 text-slate-900">
                                    <th rowspan="2" class="border-r border-slate-900 px-3 py-2 w-24 font-bold">Tanggal
                                    </th>
                                    <th rowspan="2" class="border-r border-slate-900 px-3 py-2 font-bold">Habis</th>
                                    <th colspan="5" class="border-r border-slate-900 px-3 py-2 font-bold uppercase">Kayu
                                    </th>
                                    <th colspan="5" rowspan="2" class="border-r border-slate-900 p-0 w-[352px]">
                                        <table class="w-full table-fixed border-collapse">
                                            <thead>
                                                <tr>
                                                    <th colspan="5"
                                                        class="border-b border-slate-900 py-2 text-center uppercase tracking-wider font-bold">
                                                        Veneer</th>
                                                </tr>
                                                <tr>
                                                    <th rowspan="5"
                                                        class="grid w-[352px] grid-cols-[64px_64px_48px_80px_96px] divide-x divide-slate-900 h-full min-h-[32px] items-center text-[11px] font-bold">
                                                        <div
                                                            class="text-center flex items-center justify-center min-w-16 h-full font-black">
                                                            P</div>
                                                        <div
                                                            class="text-center flex items-center justify-center min-w-16 h-full font-black">
                                                            L</div>
                                                        <div
                                                            class="text-center flex items-center justify-center min-w-12 h-full font-black">
                                                            T</div>
                                                        <div
                                                            class="text-center font-mono flex items-center justify-center min-w-20 h-full font-black">
                                                            TOTAL</div>
                                                        <div
                                                            class="bg-emerald-50/20 text-right pr-2 font-black h-full min-w-24 flex items-center justify-end">
                                                            M³</div>
                                                    </th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </th>
                                    <th rowspan="2" class="border-r border-slate-900 px-3 py-2 w-32 font-bold">Jam
                                        Kerja
                                    </th>
                                    <th rowspan="2"
                                        class="border-r border-slate-900 px-3 py-2 bg-blue-50/50 text-blue-800 font-black">
                                        %
                                    </th>
                                    <th rowspan="2"
                                        class="border-r border-slate-900 px-3 py-2 bg-emerald-100/50 text-emerald-800 font-black uppercase">
                                        Harga Veneer / m³</th>
                                    <th rowspan="2"
                                        class="border-r border-slate-900 px-3 py-2 bg-blue-50/50 text-blue-800 w-24 text-center p-0 font-bold">
                                        Pekerja</th>
                                    <th rowspan="2"
                                        class="border-r border-slate-900 px-3 py-2 bg-amber-50/50 text-amber-800 w-32 text-center p-0 font-bold">
                                        Ongkos / pkj</th>
                                    <th rowspan="2"
                                        class="border-r border-slate-900 px-3 py-2 bg-orange-100/40 text-orange-900 font-black uppercase">
                                        Harga V + Ongkos</th>
                                    <th rowspan="2"
                                        class="border-r border-slate-900 px-3 py-2 bg-blue-50/50 text-blue-800 w-32 text-center p-0 font-bold">
                                        Penyusutan</th>
                                    <th rowspan="2"
                                        class="border-r border-slate-900 px-3 py-2 bg-yellow-100/40 text-yellow-900 font-black uppercase">
                                        Harga
                                        VOP</th>
                                    @if ($adaBahanPenolong)
                                        <th rowspan="2"
                                            class="border-r border-slate-900 px-3 py-2 bg-slate-100 text-slate-800 w-32 text-center font-black uppercase">
                                            Solasi</th>
                                        <th rowspan="2"
                                            class="border-r border-slate-900 px-3 py-2 bg-lime-100/50 text-lime-800 w-32 text-center font-black uppercase">
                                            Biaya Bahan Penolong</th>
                                    @endif
                                    <th rowspan="2"
                                        class="px-3 py-2 bg-yellow-300/50 text-yellow-900 font-black uppercase">
                                        Harga Total / m³</th>
                                </tr>
                                <tr class="bg-slate-50 border-b border-slate-900 text-slate-900 uppercase">
                                    <th class="border-r border-slate-900 px-2 py-1 font-bold">Lahan</th>
                                    <th class="border-r border-slate-900 px-2 py-1 font-bold">Batang</th>
                                    <th class="border-r border-slate-900 px-2 py-1 font-bold">Pecah</th>
                                    <th
                                        class="border-r border-slate-900 px-2 py-1 bg-orange-50/30 font-bold text-orange-900">
                                        m³
                                    </th>
                                    <th
                                        class="border-r border-slate-900 px-2 py-1 bg-yellow-50/30 font-bold text-yellow-900">
                                        Poin
                                    </th>
                                </tr>

                                <tr class="bg-slate-100/80 border-b border-slate-900 text-slate-900">
                                    <th colspan="2"
                                        class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">
                                        Total</th>
                                    <th colspan="1"
                                        class="border-r border-slate-900 px-3 py-1 text-slate-900 text-center font-bold">
                                    </th>

                                    <th colspan="1"
                                        class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">
                                        {{ number_format($rekap['total_kayu_masuk'] ?? 0, 0, ',', '.') }}
                                    </th>

                                    <th colspan="1"
                                        class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">
                                        -</th>

                                    <th colspan="1"
                                        class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">
                                        {{ number_format($rekap['total_kubikasi_kayu_masuk'] ?? 0, 4, ',', '.') }}
                                    </th>

                                    <th colspan="1"
                                        class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black whitespace-nowrap">
                                        Rp {{ number_format($rekap['total_poin_masuk'] ?? 0, 0, ',', '.') }}
                                    </th>

                                    <th colspan="4" class=" bg-amber-400"></th>
                                    <th colspan="1"
                                        class="flex items-center h-full border-r border-slate-900 justify-end">
                                        <div
                                            class="min-w-24 h-full bg-amber-400 text-end justify-end border-l flex items-center border-slate-900 px-3 py-1 min-h-12 text-slate-900 font-black">
                                            {{ number_format($rekap['total_kubikasi_veneer'] ?? 0, 4, ',', '.') }}
                                        </div>

                                    </th>

                                    <th colspan="1"
                                        class="border-r whitespace-nowrap bg-[#FF88BA] border-slate-900 px-3 py-1 w-32 font-bold">
                                        Rata - Rata
                                    </th>

                                    <th colspan="1"
                                        class="border-r border-slate-900 px-3 py-1 w-32 font-bold text-blue-800">
                                        {{ $rekap['rata_rata_rendemen'] ?? '-' }}
                                    </th>

                                    <th
                                        class="border-r border-slate-900 px-3 py-1 bg-[#FF88BA] font-black text-slate-900 whitespace-nowrap">
                                        {{-- Menghitung total harga v murni dari poin / m3 veneer --}}
                                        Rp {{ number_format($rekap['total_harga_veneer'] ?? 0, 0, ',', '.') }}
                                    </th>

                                    <th colspan="2" class="border-r border-slate-900 px-3 py-1 "></th>
                                    <th
                                        class="border-r border-slate-900 px-3 py-1 bg-[#FF88BA] font-black text-slate-900 whitespace-nowrap">
                                        Rp {{ number_format($rekap['total_harga_v_ongkos'] ?? 0, 0, ',', '.') }}
                                    </th>
                                    <th colspan="1" class="border-r border-slate-900 px-3 py-1 "></th>
                                    <th
                                        class="border-r border-slate-900 px-3 py-1 bg-[#FF88BA] font-black text-slate-900 whitespace-nowrap">
                                        Rp {{ number_format($rekap['total_harga_vop'] ?? 0, 0, ',', '.') }}
                                    </th>
                                    @if ($adaBahanPenolong)
                                        <th colspan="2" class="border-r border-slate-900 px-3 py-1 "></th>
                                    @endif
                                    <th class="px-3 py-1 bg-yellow-300/60 font-black text-slate-900 whitespace-nowrap">
                                        {{-- Kolom "Harga Total / m³" baris Total: rata-rata tertimbang
                                             langsung dari rate per batch (harga_vop / harga_vopb) x
                                             kubikasi batch, dibagi total kubikasi — BUKAN total nominal
                                             dari $rekap dibagi total kubikasi lagi (itu double division).
                                             Lihat catatan lengkap di blok PHP atas. --}}
                                        Rp {{ number_format($totalHargaPerM3Semua, 0, ',', '.') }}
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-900 border-t border-slate-900 text-slate-900 font-bold">
                                <tr class="h-6 bg-slate-50">
                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    <td colspan="5" class="p-0 border-r border-slate-900 w-[352px]">
                                        <div
                                            class="grid grid-cols-[64px_64px_48px_80px_96px] divide-x divide-slate-900 h-full">
                                            <div class="w-full h-6 border-r border-slate-900"></div>
                                            <div class="w-full h-6 border-r border-slate-900"></div>
                                            <div class="w-full h-6 border-r border-slate-900"></div>
                                            <div class="w-full h-6 border-r border-slate-900"></div>
                                            <div class="w-full h-6"></div>
                                        </div>
                                    </td>

                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    <td class="border-r border-slate-900"></td>
                                    @if ($adaBahanPenolong)
                                        <td class="border-r border-slate-900"></td>
                                        <td class="border-r border-slate-900"></td>
                                    @endif
                                    <td class="border-slate-900"></td>
                                </tr>
                                @foreach ($laporan as $item)
                                    @php
                                        // Ambil tanggal outflow TERAKHIR saja (bukan semua tanggal per baris produksi),
                                        // sama seperti logika di export Excel. $item['outflow'] bisa berupa Collection
                                        // atau array biasa tergantung sumber data, jadi ditangani dua-duanya.
                                        $outflowList = $item['outflow'];
                                        $lastTgl = is_array($outflowList)
                                            ? end($outflowList)['tgl'] ?? ''
                                            : $outflowList->last()['tgl'] ?? '';

                                        // Nilai kolom "Harga Total / m³" per batch.
                                        //
                                        // FIX DOUBLE DIVISION: $item['summary']['harga_vop'] / harga_vopb
                                        // SUDAH RATE PER M³ (dihitung di
                                        // PreviewPersentaseKayu::normalizeLaporanItem() sebagai
                                        // (poin + ongkos + penyusutan) / outflowM3). Jadi di sini TIDAK
                                        // dibagi $totalM3KeluarBatch lagi — dipakai langsung apa adanya.
                                        $adaBahanDiBatchIni = ($item['summary']['total_bahan_penolong'] ?? 0) > 0;
                                        $hargaVOPorBBatch = $adaBahanDiBatchIni
                                            ? (float) $item['summary']['harga_vopb']
                                            : (float) $item['summary']['harga_vop'];
                                        $hargaTotalPerM3Batch = $hargaVOPorBBatch;
                                    @endphp
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="border-r border-slate-900 p-0">
                                            <div
                                                class="px-2 py-1 text-center text-slate-900 h-full flex items-center justify-center uppercase w-26 text-[10px] font-black">
                                                {{ $lastTgl }}
                                            </div>
                                        </td>

                                        <td
                                            class="border-r border-slate-900 px-3 py-2 text-center text-emerald-600 font-black text-lg">
                                            ✓</td>
                                        <td
                                            class="border-r border-slate-900 px-3 py-2 text-center font-black text-slate-900">
                                            {{ $item['batch_info']['kode'] }}</td>
                                        <td
                                            class="border-r border-slate-900 px-3 py-2 text-center text-slate-900 font-bold">
                                            {{ $item['summary']['total_kayu_masuk'] }}</td>
                                        <td class="border-r border-slate-900 px-3 py-2"></td>
                                        <td
                                            class="border-r border-slate-900 px-3 py-2 bg-blue-50/30 text-right font-black">
                                            {{ $item['summary']['total_masuk_m3'] }}</td>
                                        <td
                                            class="border-r border-slate-900 px-3 py-2 bg-blue-50/30 text-right tabular-nums whitespace-nowrap font-black">
                                            {{ 'Rp ' . $item['summary']['total_poin'] }}</td>

                                        <td colspan="5" class="p-0 border-r w-[352px] border-slate-900">
                                            <div class="flex flex-col divide-y w-full divide-slate-900 h-full">
                                                @foreach ($item['outflow'] as $produksi)
                                                    <div
                                                        class="grid grid-cols-[64px_64px_48px_80px_96px] w-full divide-x divide-slate-900 h-full min-h-[32px] items-center text-[11px] font-bold">
                                                        <div class="text-center flex items-center justify-center h-full">
                                                            {{ $produksi['panjang'] }}</div>
                                                        <div
                                                            class="text-center flex items-center justify-center h-full font-black text-slate-900">
                                                            {{ $produksi['lebar'] }}</div>
                                                        <div class="text-center flex items-center justify-center h-full">
                                                            {{ $produksi['tebal'] }}</div>
                                                        <div
                                                            class="text-center font-mono flex items-center justify-center h-full font-black">
                                                            {{ $produksi['total_banyak'] }}</div>
                                                        <div
                                                            class="bg-emerald-50/20 text-right pr-2 font-black h-full flex items-center justify-end text-emerald-800">
                                                            {{ $produksi['total_kubikasi'] }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>

                                        <td class="border-r border-slate-900 p-0 text-[10px]">
                                            <div class="flex flex-col divide-y divide-slate-900">
                                                @foreach ($item['outflow'] as $produksi)
                                                    <div
                                                        class="px-2 py-1 text-center min-h-[32px] flex items-center justify-center w-32 font-bold">
                                                        06:00 - 16:00</div>
                                                @endforeach
                                            </div>
                                        </td>

                                        <td
                                            class="border-r border-slate-900 px-3 py-2 bg-blue-50/30 text-center font-black text-blue-800">
                                            {{ $item['summary']['rendemen'] }}</td>
                                        <td
                                            class="border-r border-slate-900 px-3 py-2 bg-emerald-50/30 text-right font-black text-emerald-800 whitespace-nowrap">
                                            Rp. {{ number_format($item['summary']['harga_veneer'], 0, ',', '.') }}</td>

                                        <td class="border-r border-slate-900 p-0">
                                            <div class="flex flex-col divide-y divide-slate-900">
                                                @foreach ($item['outflow'] as $produksi)
                                                    <div
                                                        class="px-2 py-1 text-center min-h-[32px] flex items-center justify-center w-24 uppercase font-bold">
                                                        {{ $produksi['pekerja'] }}</div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="border-r border-slate-900 p-0 bg-amber-50/30">
                                            <div class="flex flex-col divide-y divide-slate-900">
                                                @foreach ($item['outflow'] as $produksi)
                                                    <div
                                                        class="px-2 py-1 text-right min-h-[32px] flex items-center justify-end w-32 pr-2 whitespace-nowrap font-bold">
                                                        Rp. {{ number_format($produksi['ongkos'], 0, ',', '.') }}</div>
                                                @endforeach
                                            </div>
                                        </td>

                                        <td
                                            class="border-r border-slate-900 px-3 py-2 bg-orange-50/40 text-right font-black text-orange-900 whitespace-nowrap">
                                            Rp. {{ number_format($item['summary']['harga_v_ongkos'], 0, ',', '.') }}</td>

                                        <td class="border-r border-slate-900 p-0 bg-blue-50/30">
                                            <div class="flex flex-col divide-y divide-slate-900">
                                                @foreach ($item['outflow'] as $produksi)
                                                    <div
                                                        class="px-2 py-1 text-right min-h-[32px] flex items-center justify-end w-32 pr-2 whitespace-nowrap font-bold">
                                                        Rp. {{ number_format($produksi['penyusutan'], 0, ',', '.') }}</div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td
                                            class="border-r border-slate-900 px-3 py-2 bg-yellow-50/50 text-right font-black text-slate-900 whitespace-nowrap">
                                            Rp. {{ number_format($item['summary']['harga_vop'], 0, ',', '.') }}</td>

                                        @if ($adaBahanPenolong)
                                            {{-- Kolom Solasi: NOMINAL LANGSUNG per baris outflow (jumlah roll
                                                 dibulatkan normal, dikali harga_satuan) — Opsi B, konsisten
                                                 dengan Blade index & export Excel. --}}
                                            <td class="border-r border-slate-900 p-0 bg-slate-50/60 text-[10px]">
                                                <div class="flex flex-col divide-y divide-slate-900">
                                                    @foreach ($item['outflow'] as $produksi)
                                                        <div
                                                            class="px-2 py-1 text-center min-h-[32px] flex items-center justify-center w-32 font-bold">
                                                            @forelse (($produksi['bahan_penolong'] ?? []) as $bp)
                                                                Rp
                                                                {{ number_format(round($bp['jumlah'] ?? 0) * ($bp['harga_satuan'] ?? 0), 0, ',', '.') }}
                                                                @if (!$loop->last)
                                                                    ,
                                                                @endif
                                                            @empty
                                                                <span class="text-slate-400 font-normal">-</span>
                                                            @endforelse
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>

                                            {{-- Kolom Biaya Bahan Penolong per m³, per baris outflow (tetap
                                                 pakai jumlah desimal asli/subtotal, TIDAK ikut dibulatkan
                                                 seperti kolom Solasi di atas). --}}
                                            <td class="border-r border-slate-900 p-0 bg-lime-50/40">
                                                <div class="flex flex-col divide-y divide-slate-900">
                                                    @foreach ($item['outflow'] as $produksi)
                                                        @php
                                                            $subtotalBahanBaris = collect(
                                                                $produksi['bahan_penolong'] ?? [],
                                                            )->sum('subtotal');
                                                            $kubikasiBaris = (float) str_replace(
                                                                ',',
                                                                '',
                                                                $produksi['total_kubikasi'] ?? 0,
                                                            );
                                                            $bahanPerM3Baris =
                                                                $kubikasiBaris > 0
                                                                    ? $subtotalBahanBaris / $kubikasiBaris
                                                                    : 0;
                                                        @endphp
                                                        <div
                                                            class="px-2 py-1 text-right min-h-[32px] flex items-center justify-end w-32 pr-2 whitespace-nowrap font-bold">
                                                            @if ($subtotalBahanBaris > 0)
                                                                Rp. {{ number_format($bahanPerM3Baris, 0, ',', '.') }}
                                                            @else
                                                                <span class="text-slate-400 font-normal">-</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endif

                                        {{-- Kolom "Harga Total / m³": SELALU ada, satu nilai per batch.
                                             Sudah rate per m³ langsung dari harga_vop / harga_vopb,
                                             TANPA dibagi kubikasi lagi (lihat catatan di blok PHP). --}}
                                        <td
                                            class="px-3 py-2 bg-yellow-300/40 text-right font-black text-slate-900 whitespace-nowrap">
                                            Rp. {{ number_format($hargaTotalPerM3Batch, 0, ',', '.') }}</td>
                                    </tr>

                                    @if (!$loop->last)
                                        <tr class="h-6 bg-slate-50">
                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            <td colspan="5" class="p-0 border-r border-slate-900 w-[352px] h-full">
                                                <div
                                                    class="grid grid-cols-[64px_64px_48px_80px_96px] divide-x divide-slate-900 h-full">
                                                    <div class="w-full h-6  border-r border-slate-900"></div>
                                                    <div class="w-full h-6  border-r border-slate-900"></div>
                                                    <div class="w-full h-6  border-r border-slate-900"></div>
                                                    <div class="w-full h-6  border-r border-slate-900"></div>
                                                    <div class="w-full h-6 "></div>
                                                </div>
                                            </td>

                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            <td class="border-r border-slate-900"></td>
                                            @if ($adaBahanPenolong)
                                                <td class="border-r border-slate-900"></td>
                                                <td class="border-r border-slate-900"></td>
                                            @endif
                                            <td class="border-slate-900"></td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endfragment
            </div>
        </div>

        <div class="mt-4 text-[10px] text-slate-600 font-bold uppercase">
            * Generated automatically by Veneer Production System - Export Preview Mode
        </div>

        <div
            class="fixed bottom-0 left-0 right-0 bg-[#217346] border-t border-slate-900 flex items-center px-4 no-print z-50 h-10 shadow-[0_-2px_10px_rgba(0,0,0,0.1)]">
            <div class="flex border-r border-green-800 pr-2 mr-2">
                <button class="p-1 hover:bg-green-700 text-white font-black"><svg class="w-4 h-4" fill="currentColor"
                        viewBox="0 0 20 20">
                        <path
                            d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" />
                    </svg></button>
                <button class="p-1 hover:bg-green-700 text-white font-black"><svg class="w-4 h-4" fill="currentColor"
                        viewBox="0 0 20 20">
                        <path
                            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" />
                    </svg></button>
            </div>

            <div class="flex items-end h-full">
                @foreach ($sheets as $sheet)
                    <a href="{{ url()->current() }}?bulan={{ $selectedBulan }}&tahun={{ $selectedTahun }}&sheet={{ urlencode($sheet) }}"
                        data-bulan="{{ $selectedBulan }}" data-tahun="{{ $selectedTahun }}"
                        data-sheet="{{ $sheet }}"
                        class="sheet-tab-link px-4 py-1 text-xs font-black transition-all flex items-center h-[85%] 
                    {{ $activeSheet == $sheet
                        ? 'bg-white text-green-800 border-x border-t border-slate-400 rounded-t shadow-sm'
                        : 'text-white hover:bg-green-700 border-x border-transparent' }}">
                        KAYU {{ $sheet }}
                    </a>
                @endforeach
            </div>

            <div class="ml-auto text-[10px] text-green-100 font-black font-mono uppercase">
                Veneer System v2.0
            </div>
        </div>
    </div>

    <script>
        (function() {
            var tableWrapper = document.getElementById('table-wrapper');
            var tableOverlay = document.getElementById('table-loading-overlay');
            var tableFragmentContainer = document.getElementById('table-fragment-container');
            var filterForm = document.getElementById('filter-form');
            var exportBtn = document.getElementById('export-excel-btn');
            var bulanSelect = document.getElementById('filter-bulan');
            var tahunSelect = document.getElementById('filter-tahun');
            var sheetHiddenInput = document.getElementById('filter-sheet');
            var inlineLoading = document.getElementById('filter-inline-loading');
            var exportUrlBase = exportBtn ? exportBtn.getAttribute('data-export-url-base') : '';

            function showTableLoading() {
                if (tableOverlay) {
                    tableOverlay.classList.remove('hidden');
                    tableOverlay.classList.add('flex');
                }
                if (tableWrapper) {
                    tableWrapper.setAttribute('aria-busy', 'true');
                    tableWrapper.style.pointerEvents = 'none';
                }
            }

            function hideTableLoading() {
                if (tableOverlay) {
                    tableOverlay.classList.add('hidden');
                    tableOverlay.classList.remove('flex');
                }
                if (tableWrapper) {
                    tableWrapper.removeAttribute('aria-busy');
                    tableWrapper.style.pointerEvents = '';
                }
            }

            function showInlineLoading() {
                if (inlineLoading) {
                    inlineLoading.classList.remove('hidden');
                    inlineLoading.classList.add('flex');
                }
            }

            function hideInlineLoading() {
                if (inlineLoading) {
                    inlineLoading.classList.add('hidden');
                    inlineLoading.classList.remove('flex');
                }
            }

            // Ambil query params saat ini, override dengan overrides yang dikasih.
            function buildParams(overrides) {
                var params = new URLSearchParams(window.location.search);
                Object.keys(overrides).forEach(function(key) {
                    params.set(key, overrides[key]);
                });
                return params;
            }

            // *** KUNCI FIX BUG TOMBOL EXPORT ***
            // Setelah fragment tabel baru disuntikkan ke DOM, baca atribut
            // data-sheets-empty & data-export-query yang dikirim server di
            // dalam fragment tsb (nilai TERBARU sesuai bulan/tahun/sheet yang
            // baru difetch), lalu update SATU tombol export yang sama:
            // toggle warna, label, cursor, href, dan disabled state-nya.
            function syncExportButton() {
                if (!exportBtn) return;

                var flagHolder = tableFragmentContainer.querySelector('[data-sheets-empty]');
                var isEmpty = flagHolder ? flagHolder.getAttribute('data-sheets-empty') === '1' : true;
                var exportQuery = flagHolder ? flagHolder.getAttribute('data-export-query') : '';

                var label = exportBtn.querySelector('.export-label');

                exportBtn.setAttribute('data-empty', isEmpty ? '1' : '0');

                if (isEmpty) {
                    exportBtn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
                    exportBtn.classList.add('bg-slate-400', 'cursor-not-allowed');
                    exportBtn.setAttribute('href', '#');
                    if (label) label.textContent = 'EXPORT EXCEL (KOSONG)';
                } else {
                    exportBtn.classList.remove('bg-slate-400', 'cursor-not-allowed');
                    exportBtn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
                    exportBtn.setAttribute('href', exportUrlBase + '?' + exportQuery);
                    if (label) label.textContent = 'EXPORT EXCEL';
                }
            }

            // Inti dari LAZY LOAD: fetch tabel via AJAX (controller mengembalikan
            // HANYA fragment table-content lewat ->fragment('table-content')),
            // lalu suntikkan ke dalam #table-fragment-container.
            // Dipakai untuk: first load, ganti bulan/tahun, dan pindah tab sheet.
            function loadTable(overrides, pushUrl) {
                showTableLoading();
                showInlineLoading();

                var params = buildParams(overrides || {});
                var url = window.location.pathname + '?' + params.toString();

                fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(res) {
                        if (!res.ok) throw new Error('Gagal memuat data (' + res.status + ')');
                        return res.text();
                    })
                    .then(function(html) {
                        tableFragmentContainer.innerHTML = html;
                        syncExportButton();
                        if (pushUrl !== false) {
                            window.history.replaceState({}, '', url);
                        }
                    })
                    .catch(function(err) {
                        tableFragmentContainer.innerHTML =
                            '<div class="p-6 text-center text-red-600 font-black">' +
                            'Gagal memuat data. Coba ganti ulang filter.</div>';
                        console.error(err);
                    })
                    .finally(function() {
                        hideTableLoading();
                        hideInlineLoading();
                    });
            }

            // Ganti bulan/tahun -> fetch ulang, TIDAK reload halaman & TIDAK
            // mengunci dropdown, jadi user tetap bebas ganti-ganti lagi.
            [bulanSelect, tahunSelect].forEach(function(select) {
                if (select) {
                    select.addEventListener('change', function() {
                        loadTable({
                            bulan: bulanSelect.value,
                            tahun: tahunSelect.value,
                            sheet: sheetHiddenInput.value
                        });
                    });
                }
            });

            // Cegah submit form biasa (misal user tekan Enter), arahkan ke loadTable juga.
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    loadTable({
                        bulan: bulanSelect.value,
                        tahun: tahunSelect.value,
                        sheet: sheetHiddenInput.value
                    });
                });
            }

            // Tab pindah sheet -> fetch ulang juga, bukan navigasi penuh.
            document.querySelectorAll('.sheet-tab-link').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var sheet = link.getAttribute('data-sheet');
                    sheetHiddenInput.value = sheet;
                    loadTable({
                        bulan: link.getAttribute('data-bulan'),
                        tahun: link.getAttribute('data-tahun'),
                        sheet: sheet
                    });

                    // Update tampilan tab aktif secara instan tanpa nunggu response
                    document.querySelectorAll('.sheet-tab-link').forEach(function(l) {
                        l.classList.remove('bg-white', 'text-green-800', 'border-x', 'border-t',
                            'border-slate-400', 'rounded-t', 'shadow-sm');
                        l.classList.add('text-white', 'border-x', 'border-transparent');
                    });
                    link.classList.add('bg-white', 'text-green-800', 'border-x', 'border-t',
                        'border-slate-400', 'rounded-t', 'shadow-sm');
                    link.classList.remove('text-white', 'border-transparent');
                });
            });

            // Klik tombol export: hanya jalan kalau state-nya TIDAK kosong.
            // Kalau kosong, tampilkan alert & cegah navigasi (karena href="#").
            if (exportBtn) {
                exportBtn.addEventListener('click', function(e) {
                    var isEmpty = exportBtn.getAttribute('data-empty') === '1';

                    if (isEmpty) {
                        e.preventDefault();
                        alert('Maaf, data kosong. Tidak ada laporan yang bisa di-export untuk periode ini.');
                        return;
                    }

                    var icon = exportBtn.querySelector('.export-icon');
                    var spinner = exportBtn.querySelector('.export-spinner');
                    var label = exportBtn.querySelector('.export-label');

                    if (icon) icon.classList.add('hidden');
                    if (spinner) spinner.classList.remove('hidden');
                    if (label) label.textContent = 'MENYIAPKAN...';

                    setTimeout(function() {
                        if (icon) icon.classList.remove('hidden');
                        if (spinner) spinner.classList.add('hidden');
                        if (label) label.textContent = 'EXPORT EXCEL';
                    }, 2500);
                });
            }

            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    hideTableLoading();
                    hideInlineLoading();
                }
            });

            // *** INI KUNCI PERMINTAAN KAMU ***
            // First load pun lewat jalur AJAX yang SAMA seperti ganti filter:
            // begitu shell halaman selesai render, langsung fetch tabel.
            // Nilai default (bulan/tahun/sheet) diambil dari server, karena URL
            // awal saat pertama buka halaman bisa saja belum punya query string
            // sama sekali.
            loadTable({
                bulan: @json($selectedBulan),
                tahun: @json($selectedTahun),
                sheet: @json($activeSheet)
            }, true);
        })();
    </script>
</body>

</html>
