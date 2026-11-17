{{-- resources/views/filament/pages/hpp-veneer-jadi-page.blade.php — tampilan disamakan dengan hpp-veneer-basah-page --}}
<x-filament-panels::page>

    @php
        $logs        = $this->logs;       // paginator, 50 baris per halaman
        $r           = $this->ringkasan;  // total seluruh hasil filter (dihitung di database)
        $totalMasuk  = $r['masuk_lbr'];
        $totalKeluar = $r['keluar_lbr'];
        $saldoLembar = $totalMasuk - $totalKeluar;
        $lastLog     = $r['last'];        // log terbaru dari seluruh hasil filter
        $asalLog     = $this->asalUntukLogs($logs);
        $ringkas     = $this->hppRingkas;   // angka utama (jika ukuran sudah lengkap)
        $perUkuran   = $this->hppPerUkuran; // rekap per ukuran (jika belum lengkap)
        $jalurLabel  = \App\Filament\Pages\HppVeneerJadiPage::JALUR;
        $tandaSementara = \App\Services\HppVeneerJadiService::TANDA_SEMENTARA;
    @endphp

    {{-- Filter bar --}}
    <div x-data="{ info: false }" class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 p-3 mb-5 flex items-center gap-3 flex-wrap">
        <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Filter:</span>

        <select wire:model.live="filterJenisKayu"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Jenis Kayu</option>
            @foreach(\App\Models\JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id') as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterPanjang"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Panjang</option>
            @foreach($this->ukuranList->pluck('panjang')->unique()->sort() as $p)
                <option value="{{ $p }}">{{ $p }} cm</option>
            @endforeach
        </select>

        <select wire:model.live="filterLebar"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Lebar</option>
            @foreach($this->ukuranList->pluck('lebar')->unique()->sort() as $l)
                <option value="{{ $l }}">{{ $l }} cm</option>
            @endforeach
        </select>

        <select wire:model.live="filterTebal"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Tebal</option>
            @foreach($this->ukuranList->pluck('tebal')->unique()->sort() as $t)
                <option value="{{ $t }}">{{ $t }} mm</option>
            @endforeach
        </select>

        <select wire:model.live="filterJalur"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Jalur</option>
            @foreach($jalurLabel as $kode => $label)
                <option value="{{ $kode }}">{{ $label }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterBulan"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Bulan</option>
            @foreach($this->bulanList as $bln)
                <option value="{{ $bln }}">{{ \Carbon\Carbon::createFromFormat('Y-m', $bln)->locale('id')->translatedFormat('F Y') }}</option>
            @endforeach
        </select>

        {{-- Filter Rentang Tanggal --}}
        <div class="flex items-center gap-1">
            <input type="date" wire:model.live="filterTanggalMulai" title="Tanggal Mulai"
                class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-2 py-1.5 outline-none focus:border-primary-500">
            <span class="text-xs text-gray-400 font-bold">-</span>
            <input type="date" wire:model.live="filterTanggalSelesai" title="Tanggal Selesai"
                class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-2 py-1.5 outline-none focus:border-primary-500">
        </div>

        <input wire:model.live.debounce.400ms="filterKw" placeholder="Cari kayu/ukuran/KW"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500 w-36" />

        <div class="ml-auto flex items-center gap-3">
            @if($filterJenisKayu !== '' || $filterPanjang !== '' || $filterLebar !== '' || $filterTebal !== '' || $filterJalur !== '' || $filterBulan !== '' || $filterTanggalMulai !== '' || $filterTanggalSelesai !== '' || trim($filterKw) !== '')
                <button type="button" wire:click="resetFilter"
                    class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1.5 rounded-sm border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:border-amber-400 hover:text-amber-600">
                    ✕ Reset
                </button>
            @endif

            <label class="flex items-center gap-1.5 cursor-pointer select-none text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                <input type="checkbox" wire:model.live="tampilNilaiStok" style="accent-color:#f59e0b;width:14px;height:14px;">
                Nilai stok
            </label>

            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ number_format($logs->total()) }} entri log</span>

            <button type="button" @click="info = true" title="Cara membaca halaman ini"
                style="width:22px;height:22px;border-radius:9999px;border:1.5px solid #f59e0b;color:#f59e0b;font-weight:900;font-size:12px;line-height:1;display:inline-flex;align-items:center;justify-content:center;">!</button>
        </div>

        {{-- Popup: cara membaca halaman --}}
        <div x-show="info" x-cloak x-transition.opacity
             @keydown.escape.window="info = false" @click.self="info = false"
             style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:60;background:rgba(0,0,0,.6);overflow-y:auto;padding:16px;">
            <div class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600 rounded-md shadow-xl"
                 style="max-width:680px;margin:5vh auto;padding:22px 24px;">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-black uppercase tracking-widest">Cara membaca halaman ini</h3>
                    <button type="button" @click="info = false" class="text-gray-400 hover:text-gray-200 text-lg leading-none">✕</button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Halaman ini mencatat setiap veneer jadi yang masuk dan keluar gudang, lengkap dengan harga pokok (HPP) per m³-nya.
                </p>

                <div class="space-y-4 text-xs leading-relaxed">
                    <div>
                        <div class="font-black text-gray-900 dark:text-white">1. Satu baris = satu kejadian</div>
                        <b class="text-emerald-600 dark:text-emerald-400">MASUK</b>: veneer jadi diterima gudang (langsung dari veneer kering, hasil repair, atau hasil joint).
                        <b class="text-orange-600 dark:text-orange-400">KELUAR</b>: veneer jadi dikirim ke repair / joint / hot press, atau berkurang karena opname.
                    </div>

                    <div>
                        <div class="font-black text-gray-900 dark:text-white">2. Komponen: biaya 1 m³ veneer jadi dari kejadian itu</div>
                        Dipecah seperti veneer basah:
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">K</span> kayu,
                        <span class="text-blue-600 dark:text-blue-400 font-bold">P</span> pekerja,
                        <span class="text-purple-600 dark:text-purple-400 font-bold">M</span> mesin,
                        <span class="text-orange-600 dark:text-orange-400 font-bold">B</span> bahan penolong,
                        dan <span class="text-gray-500 font-bold">L</span> (muncul bila ada bagian tanpa rincian, misalnya stok awal, opname, atau nota).
                        Angka tebal adalah jumlah seluruhnya. Huruf kotak abu-abu adalah kode lahan asal kayu (dengan persentasenya bila lebih dari satu lahan),
                        dan label <b>Dryer / Kedi</b> menunjukkan veneer kering itu berasal dari mesin yang mana.

                        <div class="mt-2 font-bold text-gray-800 dark:text-gray-100">Dari mana angka K, P, M, B didapat?</div>
                        Semua angka <b>dibawa dari tahap sebelumnya</b> (veneer basah → dryer/kedi), lalu ditambah biaya proses jalur yang dilewati:

                        <div class="mt-2 space-y-2">
                            <div>
                                <span class="font-black">Kering langsung</span>: hanya HPP veneer kering dari batch itu sendiri
                                (HPP Press Dryer dan Kedi <b>tidak</b> dirata-rata, tiap batch memakai angkanya masing-masing).
                                Bila diambil dari gudang kering, memakai HPP Average veneer kering.
                            </div>
                            <div>
                                <span class="font-black">Repair</span>: HPP veneer kering yang dimasukkan ke repair
                                + <span class="text-blue-600 dark:text-blue-400 font-black">P</span> (upah pekerja repair hari itu ÷ m³ hasil)
                                + <span class="text-orange-600 dark:text-orange-400 font-black">B</span> (bahan penolong repair × harga master ÷ m³ hasil).
                                Repair tidak punya biaya mesin.
                            </div>
                            <div>
                                <span class="font-black">Joint</span>: HPP veneer kering yang dimasukkan ke joint
                                + <span class="text-blue-600 dark:text-blue-400 font-black">P</span> (upah pekerja joint ÷ m³ hasil)
                                + <span class="text-purple-600 dark:text-purple-400 font-black">M</span> (penyusutan mesin kategori Joint dari master <b>Mesin</b>, dibebankan penuh satu kali per produksi ÷ m³ hasil)
                                + <span class="text-orange-600 dark:text-orange-400 font-black">B</span> (tepung, lem pai, dll × harga master ÷ m³ hasil).
                            </div>
                        </div>

                        <div class="mt-2 px-3 py-2 rounded-sm bg-gray-50 dark:bg-gray-900 text-[11px] leading-relaxed">
                            <div class="font-black mb-1 text-gray-500">Contoh repair: 4 m³ veneer kering menjadi 3,6 m³ veneer jadi</div>
                            Nilai veneer kering 4 × Rp 1.363.333 = Rp 5.453.333<br>
                            Pekerja Rp 240.000 + bahan Rp 21.300 = Rp 261.300<br>
                            (5.453.333 + 261.300) ÷ 3,6 = <b>Rp 1.587.398</b> per m³ veneer jadi.
                        </div>
                        <div class="mt-1.5 text-gray-500 dark:text-gray-400">
                            Karena pembaginya m³ <b>hasil</b>, susut (m³ hasil lebih kecil dari m³ yang dimasukkan) otomatis menaikkan HPP per m³
                            di semua komponen, termasuk K.
                        </div>
                    </div>

                    <div>
                        <div class="font-black text-gray-900 dark:text-white">3. Nilai Stok dan HPP Average</div>
                        <b>Nilai Stok</b> = harga seluruh veneer jadi ukuran + KW itu yang ada di gudang, dalam Rupiah.
                        <span class="text-gray-500 dark:text-gray-400">(Kolomnya disembunyikan; centang "Nilai stok" di kanan filter untuk menampilkannya.)</span><br>
                        <b class="text-amber-600 dark:text-amber-400">HPP Average</b> = harga rata-rata 1 m³ di gudang
                        = <span class="font-mono">Nilai Stok ÷ Stok Kubikasi</span>.
                        Rincian K/P/M/B, lahan, dan mesin ikut dirata-rata dengan bobot m³ yang sama.
                        Veneer yang keluar tidak mengubah HPP Average, hanya mengurangi stok dan nilai stok.
                    </div>

                    <div>
                        <div class="font-black text-gray-900 dark:text-white">4. Label FINAL atau ESTIMASI</div>
                        <b class="text-emerald-600 dark:text-emerald-400">FINAL</b>: modal repair / produksi joint sudah selesai, susut sudah diperhitungkan.<br>
                        <b class="text-amber-600 dark:text-amber-400">ESTIMASI</b>: produksi belum selesai, jadi susut belum masuk hitungan.
                    </div>

                    <div>
                        <div class="font-black text-gray-900 dark:text-white">5. Kotak HPP di atas tabel</div>
                        Rata-rata biaya veneer jadi yang <b>masuk</b> pada bulan yang dipilih (dibobot dengan jumlah m³).
                        Pilih jenis kayu dan ukuran lengkap untuk mendapat satu angka. Tombol <b>✕ Reset</b> mengosongkan semua filter.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Rekap HPP: satu angka untuk satu ukuran, atau daftar ukuran dengan HPP-nya --}}
    @php
        $d = $ringkas['data'];
        $labelBulan = $ringkas['bulan']
            ? \Carbon\Carbon::createFromFormat('Y-m', $ringkas['bulan'])->locale('id')->translatedFormat('F Y')
            : '-';
    @endphp
    <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 shadow-sm mb-5 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center gap-2 flex-wrap">
            <span class="text-[11px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-200">HPP Veneer Jadi · {{ $labelBulan }}</span>
            @if($ringkas['otomatis'])
                <span class="text-[10px] text-gray-400">bulan terbaru · ganti lewat filter Bulan</span>
            @endif
        </div>

        @if($ringkas['lengkap'])
            <div class="px-5 py-4">
                <div class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $ringkas['jenis'] }} {{ $ringkas['ukuran'] }}</div>
                @if($d)
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-3xl font-black text-amber-600 dark:text-amber-400 tabular-nums">Rp {{ number_format($d['total'], 0, ',', '.') }}</span>
                        <span class="text-sm text-gray-400">per m³</span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                        {{ number_format($d['transaksi']) }} transaksi · {{ number_format($d['m3'], 2, ',', '.') }} m³ masuk
                        · Kayu {{ number_format($d['kayu'], 0, ',', '.') }}
                        · Pekerja {{ number_format($d['pekerja'], 0, ',', '.') }}
                        · Mesin {{ number_format($d['mesin'], 0, ',', '.') }}
                        · Bahan {{ number_format($d['bahan'], 0, ',', '.') }}
                        @if($d['lainnya'] > 0) · Tanpa rincian {{ number_format($d['lainnya'], 0, ',', '.') }} @endif
                    </div>
                    @if($d['sementara'] > 0)
                        <div class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">
                            {{ $d['sementara'] }} dari {{ $d['transaksi'] }} transaksi masih estimasi (produksi belum selesai).
                        </div>
                    @endif
                @else
                    <div class="mt-2 text-sm text-gray-400">Tidak ada veneer jadi masuk di bulan ini untuk ukuran ini.</div>
                @endif
            </div>
        @elseif(count($perUkuran))
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-[10px] font-black uppercase tracking-widest text-gray-400 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-5 py-2 text-left">Jenis</th>
                        <th class="px-5 py-2 text-left">Ukuran</th>
                        <th class="px-5 py-2 text-right">m³ masuk</th>
                        <th class="px-5 py-2 text-right bg-amber-50/50 dark:bg-amber-900/10">HPP per m³</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($perUkuran as $u)
                        <tr wire:click="pilihUkuran('{{ $u['jenis_id'] }}', '{{ $u['panjang'] }}', '{{ $u['lebar'] }}', '{{ $u['tebal'] }}')"
                            class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/40 tabular-nums">
                            <td class="px-5 py-2 font-bold text-gray-800 dark:text-gray-200">{{ $u['jenis'] }}</td>
                            <td class="px-5 py-2 text-gray-600 dark:text-gray-300">{{ $u['ukuran'] }}</td>
                            <td class="px-5 py-2 text-right text-gray-500">{{ number_format($u['m3'], 2, ',', '.') }}</td>
                            <td class="px-5 py-2 text-right font-black text-amber-700 dark:text-amber-400 bg-amber-50/30 dark:bg-amber-900/5">
                                {{ number_format($u['total'], 0, ',', '.') }}{{ $u['sementara'] > 0 ? ' *' : '' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-5 py-2 text-[10px] text-gray-400 border-t border-gray-100 dark:border-gray-700">
                Klik satu baris untuk melihat log ukuran itu.
                @if(collect($perUkuran)->sum('sementara') > 0) &nbsp;* sebagian transaksi masih estimasi (produksi belum selesai). @endif
            </div>
        @else
            <div class="px-5 py-5 text-sm text-gray-400">Belum ada veneer jadi masuk berHPP di bulan ini untuk filter ini.</div>
        @endif
    </div>

    {{-- Summary bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">

        <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center gap-3 flex-wrap">
            <span class="inline-flex items-center gap-1 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 text-[10px] font-black px-2.5 py-1 rounded-sm uppercase tracking-tighter">
                ↑ {{ number_format($totalMasuk) }} lbr masuk
            </span>
            <span class="inline-flex items-center gap-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 text-[10px] font-black px-2.5 py-1 rounded-sm uppercase tracking-tighter">
                ↓ {{ number_format($totalKeluar) }} lbr keluar
            </span>
            <span class="inline-flex items-center gap-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-[10px] font-black px-2.5 py-1 rounded-sm uppercase tracking-tighter">
                = {{ number_format($saldoLembar) }} saldo
            </span>
            @if($lastLog)
                <span class="ml-auto inline-flex items-center gap-1 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-300 text-[10px] font-black px-3 py-1 rounded-sm uppercase tracking-tight">
                    HPP terakhir: Rp {{ number_format($lastLog->hpp_average, 0, ',', '.') }}/m³
                </span>
            @endif
        </div>

        {{-- Tabel log --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-900 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-4 py-3 text-left whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left whitespace-nowrap">Jenis Kayu</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Ukuran</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">KW</th>
                        <th class="px-4 py-3 text-left whitespace-nowrap">Tipe</th>
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Qty<div class="text-[10px] font-medium normal-case text-gray-400 tracking-normal">lembar</div>
                        </th>
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Stok Lembar<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">Sebelum → Sesudah</div>
                        </th>
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Kubikasi<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">masuk/keluar</div>
                        </th>
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Stok Kubikasi<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">Sebelum → Sesudah</div>
                        </th>
                        @if($tampilNilaiStok)
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Nilai Stok<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">Rp · Sebelum → Sesudah</div>
                        </th>
                        @endif
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 bg-blue-50/30 dark:bg-blue-900/5 whitespace-nowrap">
                            Komponen HPP/m³<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">kayu · pekerja · mesin · bahan</div>
                        </th>
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 bg-amber-50/50 dark:bg-amber-900/10 whitespace-nowrap">
                            HPP Average<div class="text-[10px] font-medium normal-case text-amber-500 tracking-normal">per m³</div>
                        </th>
                        <th class="px-4 py-3 text-left">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($logs as $log)
                    @php $isM = $log->tipe_transaksi === 'masuk'; @endphp
                    <tr @class(['transition',
                        'hover:bg-green-50/30 dark:hover:bg-green-900/10' => $isM,
                        'hover:bg-red-50/30 dark:hover:bg-red-900/10'     => !$isM])>

                        <td class="px-4 py-2 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($log->tanggal)->format('d/m/Y') }}
                        </td>

                        <td class="px-4 py-2 font-black text-gray-900 dark:text-white whitespace-nowrap">
                            {{ $log->jenisKayu?->nama_kayu ?? '-' }}
                        </td>

                        <td class="px-4 py-2 text-right font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap tabular-nums">
                            {{ (float)$log->panjang }}×{{ (float)$log->lebar }}×{{ (float)$log->tebal }}
                        </td>

                        <td class="px-4 py-2 text-center whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase tracking-tight bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                {{ $log->kw_grade ?? '-' }}
                            </span>
                        </td>

                        <td class="px-4 py-2 whitespace-nowrap">
                            <span @class(['inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase tracking-tight',
                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $isM,
                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'         => !$isM])>
                                {{ $isM ? '↑ Masuk' : '↓ Keluar' }}
                            </span>
                        </td>

                        <td @class(['px-4 py-2 text-right font-black text-sm border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums',
                            'text-green-600 dark:text-green-400' => $isM,
                            'text-red-600 dark:text-red-400'     => !$isM])>
                            {{ $isM ? '+' : '-' }}{{ number_format($log->total_lembar) }}
                        </td>

                        <td class="px-4 py-2 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5 font-mono text-xs tabular-nums">
                                <span class="text-gray-400 dark:text-gray-500">{{ number_format($log->stok_lembar_before) }}</span>
                                <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                <span @class(['font-black',
                                    'text-green-600 dark:text-green-400' => $isM,
                                    'text-red-600 dark:text-red-400'     => !$isM])>
                                    {{ number_format($log->stok_lembar_after) }}
                                </span>
                            </div>
                        </td>

                        <td class="px-4 py-2 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums">
                            <div class="flex flex-col items-end">
                                <span @class(['font-semibold text-xs',
                                    'text-blue-600 dark:text-blue-400'     => $isM,
                                    'text-orange-600 dark:text-orange-400' => !$isM])>
                                    {{ number_format($log->total_kubikasi, 4) }}
                                </span>
                                <div class="text-[9px] text-gray-400 uppercase tracking-tighter">m³</div>
                            </div>
                        </td>

                        <td class="px-4 py-2 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums">
                            <div class="flex items-center justify-end gap-1.5 font-mono text-xs">
                                <span class="text-gray-400 dark:text-gray-500">{{ number_format($log->stok_kubikasi_before, 4) }}</span>
                                <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                <span @class(['font-black',
                                    'text-blue-600 dark:text-blue-400'     => $isM,
                                    'text-orange-600 dark:text-orange-400' => !$isM])>
                                    {{ number_format($log->stok_kubikasi_after, 4) }}
                                </span>
                            </div>
                        </td>

                        @if($tampilNilaiStok)
                        @php
                            $nilaiB  = (float) $log->nilai_stok_before;
                            $nilaiA  = (float) $log->nilai_stok_after;
                            $selisih = $nilaiA - $nilaiB;
                        @endphp
                        <td class="px-4 py-2 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums">
                            <div class="flex items-center justify-end gap-1.5 font-mono text-xs">
                                <span class="text-gray-400 dark:text-gray-500">{{ number_format($nilaiB, 0, ',', '.') }}</span>
                                <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                <span class="font-black text-gray-800 dark:text-gray-100">{{ number_format($nilaiA, 0, ',', '.') }}</span>
                            </div>
                            @if(abs($selisih) >= 1)
                                <div @class(['text-right text-[10px] font-mono',
                                    'text-emerald-600 dark:text-emerald-400' => $selisih > 0,
                                    'text-red-500 dark:text-red-400'         => $selisih < 0])>
                                    {{ $selisih > 0 ? '+' : '−' }}{{ number_format(abs($selisih), 0, ',', '.') }}
                                </div>
                            @endif
                        </td>
                        @endif

                        {{-- Komponen HPP: baris 1 = lahan · mesin · jalur · total · status, baris 2 = K/P/M/B(/L) --}}
                        @php
                            $asal      = $asalLog[$log->id] ?? ['lahan' => [], 'mesin' => []];
                            $estimasi  = str_contains((string) $log->keterangan, $tandaSementara);
                            $totalKomp = $isM ? ($log->hpp_kayu + $log->hpp_pekerja + $log->hpp_mesin + $log->hpp_bahan_penolong + $log->hpp_lainnya) : 0;
                        @endphp
                        <td class="px-4 py-2 border-l border-gray-50 dark:border-gray-800 bg-blue-50/10 dark:bg-blue-900/5 whitespace-nowrap">
                            @if($isM && $totalKomp > 0)
                                <div class="flex items-center gap-1.5 text-xs tabular-nums">
                                    @foreach($asal['lahan'] as $lh)
                                        <span title="Asal lahan kayu: {{ $lh['kode'] }} ({{ $lh['persen'] }}% dari m³)"
                                              class="px-1.5 py-0.5 rounded-sm bg-gray-100 dark:bg-gray-700 text-[10px] font-black uppercase text-gray-600 dark:text-gray-300">{{ $lh['kode'] }}{{ count($asal['lahan']) > 1 ? ' '.$lh['persen'].'%' : '' }}</span>
                                    @endforeach
                                    @foreach($asal['mesin'] as $ms)
                                        <span title="Veneer kering dari mesin {{ $ms['nama'] }} ({{ $ms['persen'] }}% dari m³)"
                                              class="px-1.5 py-0.5 rounded-sm bg-indigo-100 dark:bg-indigo-900/30 text-[9px] font-black uppercase text-indigo-600 dark:text-indigo-400">{{ $ms['nama'] }}{{ count($asal['mesin']) > 1 ? ' '.$ms['persen'].'%' : '' }}</span>
                                    @endforeach
                                    @if($log->jalur)
                                        <span class="px-1.5 py-0.5 rounded-sm bg-sky-100 dark:bg-sky-900/30 text-[9px] font-black uppercase text-sky-600 dark:text-sky-400">{{ $jalurLabel[$log->jalur] ?? $log->jalur }}</span>
                                    @endif
                                    <span class="font-black text-gray-800 dark:text-gray-100">{{ number_format($totalKomp, 0, ',', '.') }}</span>
                                    @if($estimasi)
                                        <span title="Produksi repair/joint belum selesai. Susut belum diperhitungkan; akan final setelah produksi selesai."
                                              class="px-1.5 py-0.5 rounded-sm bg-amber-100 dark:bg-amber-900/30 text-[9px] font-black uppercase text-amber-600 dark:text-amber-400">estimasi</span>
                                    @else
                                        <span title="Seluruh komponen sudah pasti."
                                              class="px-1.5 py-0.5 rounded-sm bg-emerald-100 dark:bg-emerald-900/30 text-[9px] font-black uppercase text-emerald-600 dark:text-emerald-400">final</span>
                                    @endif
                                </div>
                                <div class="mt-0.5 text-[10px] tabular-nums text-gray-400">
                                    <span class="text-emerald-600 dark:text-emerald-400">K {{ number_format($log->hpp_kayu, 0, ',', '.') }}</span> ·
                                    <span class="text-blue-600 dark:text-blue-400">P {{ number_format($log->hpp_pekerja, 0, ',', '.') }}</span> ·
                                    <span class="text-purple-600 dark:text-purple-400">M {{ number_format($log->hpp_mesin, 0, ',', '.') }}</span> ·
                                    <span class="text-orange-600 dark:text-orange-400">B {{ number_format($log->hpp_bahan_penolong, 0, ',', '.') }}</span>
                                    @if($log->hpp_lainnya > 0)
                                        · <span class="text-gray-500" title="Bagian HPP tanpa rincian (stok awal / opname / nota)">L {{ number_format($log->hpp_lainnya, 0, ',', '.') }}</span>
                                    @endif
                                </div>
                            @else
                                <div class="text-[10px] text-gray-400">—</div>
                            @endif
                        </td>

                        {{-- HPP Average --}}
                        <td class="px-4 py-2 text-right border-l border-gray-50 dark:border-gray-800 bg-amber-50/20 dark:bg-amber-900/5 whitespace-nowrap">
                            <span class="font-black text-xs text-amber-700 dark:text-amber-400 tabular-nums">
                                {{ number_format($log->hpp_average, 0, ',', '.') }}
                            </span>
                        </td>

                        <td class="px-4 py-2 text-[11px] font-black uppercase text-gray-700 dark:text-gray-300 whitespace-nowrap">
                            {{ trim(str_replace($tandaSementara, '', (string) $log->keterangan)) ?: '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $tampilNilaiStok ? 13 : 12 }}" class="px-4 py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                            Belum ada log transaksi veneer jadi
                        </td>
                    </tr>
                    @endforelse
                </tbody>

                @if($logs->count())
                @php
                    $m3Saldo    = $r['masuk_m3'] - $r['keluar_m3'];
                @endphp
                <tfoot>
                    <tr class="text-[10px] font-black border-t-2 bg-gray-50 dark:bg-gray-900/60 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 uppercase tracking-widest">
                        <td colspan="6" class="px-4 py-3 text-gray-500">Saldo Akhir</td>
                        <td @class(['px-4 py-3 text-right tabular-nums',
                            'text-green-700 dark:text-green-400' => $saldoLembar >= 0,
                            'text-red-600 dark:text-red-400'     => $saldoLembar < 0])>
                            {{ number_format($saldoLembar) }} lbr
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ $lastLog ? number_format($lastLog->stok_lembar_after) : '—' }} lbr
                        </td>
                        <td class="px-4 py-3 text-right text-gray-400">—</td>
                        <td class="px-4 py-3 text-right tabular-nums text-blue-600 dark:text-blue-400">
                            {{ number_format(max(0, $m3Saldo), 4) }} m³
                        </td>
                        @if($tampilNilaiStok)
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">
                            {{ $lastLog ? 'Rp '.number_format($lastLog->nilai_stok_after, 0, ',', '.') : '—' }}
                        </td>
                        @endif
                        <td class="px-4 py-3 bg-blue-50/10 dark:bg-blue-900/5"></td>
                        <td class="px-4 py-3 text-right tabular-nums bg-amber-50/50 dark:bg-amber-900/10 text-amber-700 dark:text-amber-400 font-black">
                            {{ $lastLog ? number_format($lastLog->hpp_average, 0, ',', '.').' /m³' : '—' }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
            {{ $logs->links() }}
        </div>
    </div>

</x-filament-panels::page>