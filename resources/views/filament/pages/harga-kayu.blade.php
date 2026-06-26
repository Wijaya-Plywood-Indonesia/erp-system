<x-filament-panels::page>
    <div
        class="bg-white dark:bg-gray-900 shadow-md border border-zinc-300 dark:border-zinc-700 rounded-lg overflow-hidden transition-all">

        {{-- Header Judul --}}
        <div class="py-6 border-b border-zinc-200 dark:border-zinc-800 text-center bg-zinc-50/50 dark:bg-zinc-800/30">
            <h1 class="text-2xl font-black text-zinc-900 dark:text-white uppercase tracking-tighter">
                @if ($filterDate)
                    HARGA KAYU TANGGAL ( {{ \Carbon\Carbon::parse($filterDate)->translatedFormat('d F Y') }} )
                @else
                    HARGA KAYU HARI INI ( {{ now()->translatedFormat('d F Y') }} )
                @endif
            </h1>
            <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400 uppercase mt-1">
                ( DOKUMEN LENGKAP / LETTER C )
            </p>
        </div>

        {{-- Filter Tanggal --}}
        <div
            class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/20 flex flex-wrap items-center gap-3">
            <label class="text-xs font-bold uppercase text-zinc-500 dark:text-zinc-400 tracking-widest">
                Lihat Harga Pada Tanggal:
            </label>

            <input type="date" wire:model.live="filterDate" max="{{ now()->toDateString() }}"
                class="text-sm font-semibold border border-zinc-300 dark:border-zinc-600 rounded-md px-3 py-1.5
                       bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white
                       focus:ring-2 focus:ring-amber-400 focus:border-amber-400 outline-none transition" />



            @if ($filterDate != now()->toDateString())
                <button type="button" wire:click="$set('filterDate', '{{ now()->toDateString() }}')"
                    class="text-xs font-bold uppercase tracking-widest px-3 py-1.5 rounded-md
                           bg-amber-400 hover:bg-amber-500 text-gray-900 transition">
                    Kembali ke Hari Ini
                </button>

                <span
                    class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                    Data Historis
                </span>
            @endif
        </div>

        {{-- Tabel --}}
        <div class="overflow-x-auto">
            @php
                $headerMatrix = $this->matrixHeader;
                $diameterRanges = $this->diameterRanges;
            @endphp

            <table class="w-full border-collapse text-xs">
                <thead>
                    {{-- BARIS 1: NAMA JENIS KAYU --}}
                    <tr class="bg-white dark:bg-gray-900 font-black text-zinc-900 dark:text-white text-center">
                        <th rowspan="2" colspan="2"
                            class="border border-zinc-300 dark:border-zinc-700 p-4 w-28 bg-zinc-100/50 dark:bg-zinc-800/50">
                            Tabel Harga
                        </th>
                        @foreach ($headerMatrix as $woodName => $lengths)
                            @php
                                $totalCols = $lengths->sum(fn($grades) => $grades->count());
                            @endphp
                            <th colspan="{{ $totalCols }}"
                                class="border border-zinc-300 dark:border-zinc-700 p-3 text-lg uppercase bg-zinc-50 dark:bg-white/5">
                                {{ $woodName }}
                            </th>
                        @endforeach
                    </tr>

                    {{-- BARIS 2: PANJANG --}}
                    <tr class="bg-white dark:bg-gray-900 font-black text-zinc-900 dark:text-white text-center">
                        @foreach ($headerMatrix as $woodName => $lengths)
                            @foreach ($lengths as $length => $grades)
                                <th colspan="{{ $grades->count() }}"
                                    class="border border-zinc-300 dark:border-zinc-700 p-2 text-base">
                                    {{ $length }}
                                </th>
                            @endforeach
                        @endforeach
                    </tr>

                    {{-- BARIS 3: GRADE & DIAMETER --}}
                    <tr
                        class="bg-zinc-100 dark:bg-zinc-800 font-bold text-zinc-700 dark:text-zinc-300 text-center uppercase tracking-widest">
                        <th colspan="2"
                            class="border border-zinc-300 dark:border-zinc-700 p-2 text-[9px] bg-zinc-200 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100">
                            Diameter
                        </th>
                        @foreach ($headerMatrix as $woodName => $lengths)
                            @foreach ($lengths as $length => $grades)
                                @foreach ($grades as $grade)
                                    <th
                                        class="border border-zinc-300 dark:border-zinc-700 p-1 w-16 {{ $grade == 1 ? 'text-zinc-900 dark:text-white' : 'text-zinc-800 dark:text-zinc-100' }}">
                                        {{ $grade == 1 ? 'A' : 'B' }}
                                    </th>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse ($diameterRanges as $range)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors text-center">
                            <td
                                class="border border-zinc-300 dark:border-zinc-700 p-2 font-bold bg-zinc-100/30 dark:bg-zinc-800/20 text-zinc-900 dark:text-zinc-100">
                                {{ number_format($range->min, 2) }}
                            </td>
                            <td
                                class="border border-zinc-300 dark:border-zinc-700 p-2 font-bold bg-zinc-100/30 dark:bg-zinc-800/20 border-r-2 text-zinc-900 dark:text-zinc-100">
                                {{ number_format($range->max, 2) }}
                            </td>

                            @foreach ($headerMatrix as $woodName => $lengths)
                                @foreach ($lengths as $length => $grades)
                                    @foreach ($grades as $grade)
                                        <td @class([
                                            'border border-zinc-300 dark:border-zinc-700 p-2 font-mono font-bold tabular-nums text-sm',
                                            'text-zinc-900 dark:text-white' => $grade == 1,
                                            'text-zinc-500 dark:text-zinc-400' => $grade != 1,
                                            'bg-zinc-50/50 dark:bg-white/5' =>
                                                $loop->parent->parent->iteration % 2 == 0,
                                        ])>
                                            {{ $this->getPriceMatrix($woodName, $length, $grade, $range->min, $range->max) }}
                                        </td>
                                    @endforeach
                                @endforeach
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="30"
                                class="p-10 text-center text-zinc-400 uppercase font-black tracking-widest italic">
                                Belum ada data di Master Harga
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="p-6 bg-amber-400 dark:bg-amber-600">
            <h3 class="text-sm font-black uppercase text-gray-900 mb-3 underline decoration-2 underline-offset-4">
                Kelengkapan dokumen terdiri dari :
            </h3>
            <ul class="text-xs font-bold text-gray-900 space-y-1 list-decimal ml-5">
                <li>Foto Copy Letter C</li>
                <li>Foto Copy KTP (sesuai nama pemilik lahan di Letter C)</li>
                <li>Nota Angkutan SAKR terbaru (tanda tangan sesuai nama pemilik lahan di Letter C)</li>
                <li>Foto lokasi tebang lengkap dengan titik koordinat</li>
            </ul>
        </div>

    </div>
</x-filament-panels::page>
