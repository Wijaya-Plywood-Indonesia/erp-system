{{-- resources/views/filament/pages/stok-barang-umum.blade.php --}}
<x-filament-panels::page>

    {{-- Filter bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 p-3 mb-5 flex items-center gap-3 flex-wrap">
        <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Filter:</span>

        <input wire:model.live.debounce.300ms="search" placeholder="Cari nama barang / kategori..."
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500 w-56" />

        <select wire:model.live="filterKategori"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Kategori</option>
            @foreach($this->kategoriList as $kat)
                <option value="{{ $kat }}">{{ $kat }}</option>
            @endforeach
        </select>

        <span class="ml-auto text-[10px] font-black uppercase tracking-widest text-gray-400">
            {{ $this->totalItem }} jenis barang
        </span>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
        <table class="w-full text-sm text-left border-separate border-spacing-0">
            <thead>
                <tr class="text-gray-400 dark:text-gray-400 uppercase text-[9px] tracking-widest font-black bg-gray-50/50 dark:bg-gray-800/50">
                    <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800 w-12">No</th>
                    <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800">Nama Barang</th>
                    <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800">Kategori</th>
                    <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800">Satuan</th>
                    <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800">Stok Saat Ini</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                @forelse($this->barangList as $item)
                @php $qty = (float) ($item->stok?->stok_qty ?? 0); @endphp
                <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <td class="px-6 py-4 text-center text-gray-300 dark:text-gray-600 font-mono text-xs">{{ $loop->iteration }}</td>

                    <td class="px-6 py-4">
                        <span class="font-bold text-gray-700 dark:text-gray-300 uppercase tracking-tight">
                            {{ $item->nama_barang }}
                        </span>
                        @if($item->keterangan)
                            <div class="text-[10px] text-gray-400 mt-0.5">{{ $item->keterangan }}</div>
                        @endif
                    </td>

                    <td class="px-6 py-4">
                        @if($item->kategori)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase tracking-tight bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                            {{ $item->kategori }}
                        </span>
                        @else
                        <span class="text-gray-300 dark:text-gray-600 text-xs">-</span>
                        @endif
                    </td>

                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase tracking-tight bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                            {{ $item->satuan }}
                        </span>
                    </td>

                    <td class="px-6 py-4 text-right">
    @php
        $qtyFormatted = rtrim(rtrim(number_format($qty, 4, '.', ','), '0'), '.');
    @endphp
    <span @class([
        'font-black tabular-nums text-lg',
        'text-gray-700 dark:text-gray-300' => $qty > 0,
        'text-red-500' => $qty <= 0,
    ])>
        {{ $qtyFormatted }}
    </span>
    <span class="text-[10px] text-gray-400 uppercase ml-1">{{ $item->satuan }}</span>
</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                        Belum ada barang umum terdaftar. Tambahkan lewat menu Master Barang Umum.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</x-filament-panels::page>