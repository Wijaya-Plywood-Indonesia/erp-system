<x-filament-panels::page>
    <div class="bg-white dark:bg-gray-900 shadow-md border border-zinc-300 dark:border-zinc-700 rounded-lg overflow-hidden transition-all">

        {{-- Header Judul --}}
        <div class="py-6 border-b border-zinc-200 dark:border-zinc-800 text-center bg-zinc-50/50 dark:bg-zinc-800/30">
            <h1 class="text-2xl font-black text-zinc-900 dark:text-white uppercase tracking-tighter">
                HARGA KAYU HARI INI ( {{ now()->translatedFormat('d F Y') }} )
            </h1>
            <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400 uppercase mt-1">
                ( DOKUMEN LENGKAP / LETTER C )
            </p>
        </div>

        <div class="overflow-x-auto">
            @php
            $headerMatrix = $this->matrixHeader;
            $diameterRanges = $this->diameterRanges;
            @endphp

            <table class="w-full border-collapse text-xs">
                <thead>
                    {{-- BARIS 1: NAMA JENIS KAYU --}}
                    <tr class="bg-white dark:bg-gray-900 font-black text-zinc-900 dark:text-white text-center">
                        {{-- PERBAIKAN: Rowspan diubah menjadi 2 --}}
                        <th rowspan="2" colspan="2" class="border border-zinc-300 dark:border-zinc-700 p-4 w-28 bg-zinc-100/50 dark:bg-zinc-800/50">
                            Tabel Harga
                        </th>
                        @foreach($headerMatrix as $woodName => $lengths)
                        @php
                        $totalCols = $lengths->sum(fn($grades) => $grades->count());
                        @endphp
                        <th colspan="{{ $totalCols }}" class="border border-zinc-300 dark:border-zinc-700 p-3 text-lg uppercase bg-zinc-50 dark:bg-white/5">
                            {{ $woodName }}
                        </th>
                        @endforeach
                    </tr>

                    {{-- BARIS 2: PANJANG UKURAN --}}
                    <tr class="bg-white dark:bg-gray-900 font-black text-zinc-900 dark:text-white text-center">
                        {{-- Kolom ini kosong karena sudah di-rowspan oleh "Tabel Harga" di atas --}}
                        @foreach($headerMatrix as $woodName => $lengths)
                        @foreach($lengths as $length => $grades)
                        <th colspan="{{ $grades->count() }}" class="border border-zinc-300 dark:border-zinc-700 p-2 text-base">
                            {{ $length }}
                        </th>
                        @endforeach
                        @endforeach
                    </tr>

                    {{-- BARIS 3: GRADE (A/B) DAN LABEL DIAMETER --}}
                    <tr class="bg-zinc-100 dark:bg-zinc-800 font-bold text-zinc-700 dark:text-zinc-300 text-center uppercase tracking-widest">
                        {{-- PERBAIKAN: Sekarang sel ini muncul tepat di bawah "Tabel Harga" --}}
                        <th colspan="2" class="border border-zinc-300 dark:border-zinc-700 p-2 text-[9px] bg-zinc-200 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100">
                            Diameter
                        </th>
                        @foreach($headerMatrix as $woodName => $lengths)
                        @foreach($lengths as $length => $grades)
                        @foreach($grades as $grade)
                        <th class="border border-zinc-300 dark:border-zinc-700 p-1 w-16 {{ $grade == 1 ? 'text-zinc-900 dark:text-white' : 'text-zinc-800 dark:text-zinc-100' }}">
                            {{ $grade == 1 ? 'A' : 'B' }}
                        </th>
                        @endforeach
                        @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($diameterRanges as $range)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors text-center">
                        {{-- KOLOM DIAMETER --}}
                        <td class="border border-zinc-300 dark:border-zinc-700 p-2 font-bold bg-zinc-100/30 dark:bg-zinc-800/20 text-zinc-900 dark:text-zinc-100">
                            {{ number_format($range->min, 2) }}
                        </td>
                        <td class="border border-zinc-300 dark:border-zinc-700 p-2 font-bold bg-zinc-100/30 dark:bg-zinc-800/20 border-r-2 text-zinc-900 dark:text-zinc-100">
                            {{ number_format($range->max, 2) }}
                        </td>

                        {{-- KOLOM DATA HARGA --}}
                        @foreach($headerMatrix as $woodName => $lengths)
                        @foreach($lengths as $length => $grades)
                        @foreach($grades as $grade)
                        <td @class([ 'border border-zinc-300 dark:border-zinc-700 p-2 font-mono font-bold tabular-nums text-sm' , 'text-zinc-900 dark:text-white'=> $grade == 1,
                            'text-zinc-500 dark:text-zinc-400' => $grade != 1,
                            'bg-zinc-50/50 dark:bg-white/5' => $loop->parent->parent->iteration % 2 == 0
                            ])>
                            {{ $this->getPriceMatrix($woodName, $length, $grade, $range->min, $range->max) }}
                        </td>
                        @endforeach
                        @endforeach
                        @endforeach
                    </tr>
                    @empty
                    <tr>
                        <td colspan="30" class="p-10 text-center text-zinc-400 uppercase font-black tracking-widest italic">
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