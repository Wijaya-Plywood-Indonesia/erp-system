<div class="space-y-6">

    <!-- HEADER BAR & TOGGLE FILTER (Asal Material) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-800">
        <div class="flex items-center gap-3">
            <div class="flex gap-2 p-1 bg-gray-100 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                <button
                    type="button"
                    wire:click="$set('sumberAsal', 'gudang')"
                    class="px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 {{ $sumberAsal === 'gudang' ? 'bg-amber-500 text-black shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                    Gudang Veneer Jadi
                </button>
                <button
                    type="button"
                    wire:click="$set('sumberAsal', 'sanding')"
                    class="px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 {{ $sumberAsal === 'sanding' ? 'bg-amber-500 text-black shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                    Produksi Sanding
                </button>
            </div>
        </div>

        <div class="text-xs font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-900 px-3 py-1.5 rounded-full border border-gray-200 dark:border-gray-800">
            Total Antrean: {{ $records->count() }} Palet
        </div>
    </div>

    @if($records->isEmpty())
    <div class="flex flex-col items-center justify-center p-12 text-center bg-gray-50 dark:bg-gray-900/30 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-800">
        <p class="text-gray-400 dark:text-gray-500 font-medium">Tidak ada data penyerahan material yang terdeteksi.</p>
    </div>
    @else

    <!-- ================= DISPLAY MOBILE (STAKED CARD LAYOUT) ================= -->
    <!-- Otomatis aktif pada layar ponsel (< md) -->
    <div class="block md:hidden space-y-4">
        @foreach($records as $record)
        @php
        $mk = $record->mutasiKeluar;
        $isDiterima = !is_null($record->diterima_by);
        $ukuran = ((float)$mk->panjang + 0) . 'x' . ((float)$mk->lebar + 0) . 'x' . ((float)$mk->tebal + 0);
        $kubikasi = number_format(($mk->panjang * $mk->lebar * $mk->tebal * $record->jumlah_lembar) / 10000000, 4);
        @endphp

        <div class="p-4 rounded-xl border transition-all {{ $isDiterima ? 'border-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-950/10 dark:border-emerald-500/20' : 'bg-white dark:bg-zinc-900/40 border-gray-200 dark:border-zinc-800' }}">

            <!-- Header Kartu: Tanggal & Badge -->
            <div class="flex justify-between items-center mb-3 pb-2 border-b border-gray-100 dark:border-zinc-800/40">
                <span class="text-xs font-mono text-gray-400">
                    {{ $mk->created_at->format('d/m/Y H:i') }}
                </span>
                @if($isDiterima)
                <span class="px-2 py-0.5 text-[10px] font-bold text-emerald-500 bg-emerald-500/10 rounded-md border border-emerald-500/20 flex items-center gap-1">
                    DONE
                </span>
                @else
                <span class="px-2 py-0.5 text-[10px] font-bold text-amber-500 bg-amber-500/10 rounded-md border border-amber-500/20">
                    ANTREAN
                </span>
                @endif
            </div>

            <!-- Spesifikasi Kayu -->
            <div class="space-y-2 mb-3">
                <div class="flex justify-between items-start">
                    <div>
                        <h4 class="font-extrabold text-gray-900 dark:text-white">{{ $mk->jenisKayu->nama_kayu }}</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ukuran: <span class="font-semibold text-amber-500">{{ $ukuran }} mm</span></p>
                    </div>
                    <div class="flex gap-1">
                        <span class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-amber-500/10 text-amber-500 border border-amber-500/20">
                            KW {{ $mk->kw_grade }}
                        </span>
                        <span class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-300">
                            PLT {{ $record->nomor_palet }}
                        </span>
                    </div>
                </div>

                <!-- Ringkasan Kuantitas -->
                <div class="grid grid-cols-2 gap-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-900/80 border border-gray-100 dark:border-zinc-800/60 text-xs">
                    <div>
                        <span class="text-gray-400 block text-[9px] uppercase font-bold">Kuantitas</span>
                        <span class="font-extrabold text-gray-900 dark:text-white">{{ number_format($record->jumlah_lembar) }} Lbr</span>
                    </div>
                    <div class="text-right">
                        <span class="text-gray-400 block text-[9px] uppercase font-bold">Volume</span>
                        <span class="font-bold text-amber-500 font-mono">{{ $kubikasi }} m³</span>
                    </div>
                </div>
            </div>

            <!-- Metadata Kolom Mandiri (Penyerah, Keterangan, Penerima) -->
            <div class="space-y-1.5 p-3 rounded-lg text-xs bg-gray-50/50 dark:bg-zinc-950/60 border border-gray-100 dark:border-zinc-900/60 text-gray-600 dark:text-neutral-400">
                <div class="flex justify-between items-center">
                    <span>Penyerah:</span>
                    <strong class="text-gray-800 dark:text-neutral-200">{{ $mk->operator->name ?? 'Admin' }}</strong>
                </div>
                <div class="flex justify-between items-start">
                    <span>Keterangan Penyerah:</span>
                    <span class="italic text-gray-800 dark:text-neutral-200">
                        {{ $mk->keterangan ? '"'.$mk->keterangan.'"' : '-' }}
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span>Penerima:</span>
                    @if($isDiterima)
                    <strong class="text-emerald-500 flex items-center gap-1 font-bold">
                        {{ $record->diterimaBy->name ?? 'Operator Hotpress' }}
                    </strong>
                    @else
                    <span class="italic text-gray-400 dark:text-gray-600">Belum diterima</span>
                    @endif
                </div>
            </div>

            <!-- Tombol Aksi Penerimaan Mobile -->
            @if(!$isDiterima)
            <button
                type="button"
                wire:click="terimaMaterialKustom({{ $record->id }})"
                wire:loading.attr="disabled"
                class="w-full mt-3 inline-flex justify-center items-center gap-2 rounded-xl bg-amber-500 hover:bg-amber-600 px-4 py-3 text-xs font-black text-black shadow-md active:scale-95 disabled:opacity-50">
                TERIMA MATERIAL
            </button>
            @endif
        </div>
        @endforeach
    </div>

    <!-- ================= DISPLAY DESKTOP (TABEL DETAILED) ================= -->
    <!-- Aktif pada layar laptop/komputer (>= md) -->
    <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-zinc-800 text-gray-500 dark:text-neutral-400 text-[11px] uppercase tracking-wider font-bold">
                    <th class="py-4 px-5">Tanggal Masuk</th>
                    <th class="py-4 px-5">Jenis Kayu</th>
                    <th class="py-4 px-5">Ukuran Dokumen</th>
                    <th class="py-4 px-5 text-center">KW</th>
                    <th class="py-4 px-5 text-center">No. Palet</th>
                    <th class="py-4 px-5 text-center">Jumlah Lembar</th>
                    <th class="py-4 px-5 text-right">Kubikasi (m³)</th>
                    <!-- PEMISAHAN KOLOM SESUAI REQUEST -->
                    <th class="py-4 px-5">Penyerah</th>
                    <th class="py-4 px-5">Keterangan Penyerah</th>
                    <th class="py-4 px-5">Penerima</th>
                    <th class="py-4 px-5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-zinc-900">
                @foreach($records as $record)
                @php
                $mk = $record->mutasiKeluar;
                $isDiterima = !is_null($record->diterima_by);
                $ukuranFormat = ((float)$mk->panjang + 0) . 'x' . ((float)$mk->lebar + 0) . 'x' . ((float)$mk->tebal + 0);
                $kubikasiFormat = number_format(($mk->panjang * $mk->lebar * $mk->tebal * $record->jumlah_lembar) / 10000000, 4);
                @endphp
                <tr class="border-b hover:bg-gray-50/50 dark:hover:bg-zinc-900 transition-all {{ $isDiterima ? 'bg-emerald-500/5 dark:bg-emerald-950/5' : '' }}">
                    <!-- Tanggal Masuk -->
                    <td class="py-4 px-5 font-mono text-xs whitespace-nowrap text-gray-500 dark:text-neutral-300">
                        {{ $mk->created_at->format('d/m/Y H:i') }}
                    </td>

                    <!-- Jenis Kayu -->
                    <td class="py-4 px-5 font-bold text-gray-950 dark:text-white">
                        {{ $mk->jenisKayu->nama_kayu }}
                    </td>

                    <!-- Ukuran Dokumen -->
                    <td class="py-4 px-5 font-semibold whitespace-nowrap text-gray-600 dark:text-neutral-300">
                        {{ $ukuranFormat }} mm
                    </td>

                    <!-- KW -->
                    <td class="py-4 px-5 text-center">
                        <span class="inline-block px-2.5 py-0.5 text-xs font-extrabold rounded bg-amber-500/10 text-amber-500 border border-amber-500/20">
                            {{ $mk->kw_grade }}
                        </span>
                    </td>

                    <!-- No Palet -->
                    <td class="py-4 px-5 text-center">
                        <span class="inline-block px-2.5 py-0.5 text-xs font-bold rounded bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300">
                            {{ $record->nomor_palet }}
                        </span>
                    </td>

                    <!-- Jumlah Lembar -->
                    <td class="py-4 px-5 text-center">
                        <span class="inline-block px-2.5 py-0.5 text-xs font-bold rounded bg-amber-500 text-black shadow-sm">
                            {{ number_format($record->jumlah_lembar) }} Lbr
                        </span>
                    </td>

                    <!-- Kubikasi -->
                    <td class="py-4 px-5 text-right font-semibold text-amber-500 font-mono">
                        {{ $kubikasiFormat }}
                    </td>

                    <!-- Penyerah (Murni Nama) -->
                    <td class="py-4 px-5 text-xs font-bold text-gray-600 dark:text-neutral-300">
                        {{ $mk->operator->name ?? 'Admin' }}
                    </td>

                    <!-- Keterangan Penyerah (Terpisah) -->
                    <td class="py-4 px-5 text-xs text-gray-500 dark:text-neutral-400">
                        @if($mk->keterangan)
                        <span class="italic font-medium">"{{ $mk->keterangan }}"</span>
                        @else
                        <span class="text-gray-300 dark:text-zinc-700 italic">-</span>
                        @endif
                    </td>

                    <!-- Penerima (Terpisah) -->
                    <td class="py-4 px-5 text-xs">
                        @if($isDiterima)
                        <span class="text-emerald-500 dark:text-emerald-400 font-bold flex items-center gap-1">
                            {{ $record->diterimaBy->name ?? 'Operator Hotpress' }}
                        </span>
                        @else
                        <span class="text-gray-300 dark:text-zinc-750 italic">-</span>
                        @endif
                    </td>

                    <!-- Aksi Tombol -->
                    <td class="py-3 px-5 text-right whitespace-nowrap">
                        @if(!$isDiterima)
                        <button
                            type="button"
                            wire:click="terimaMaterialKustom({{ $record->id }})"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 px-4 py-1.5 text-xs font-extrabold text-black transition-all shadow-sm active:scale-95 disabled:opacity-50">
                            TERIMA
                        </button>
                        @else
                        <span class="inline-flex items-center gap-1 text-emerald-500 font-bold text-xs bg-emerald-500/10 px-3 py-1.5 rounded-lg border border-emerald-500/20">
                            ✓ DONE
                        </span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>