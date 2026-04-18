<x-filament-panels::page>
    <div class="flex flex-col gap-10">
        @forelse($this->logs as $date => $items)
        <div class="space-y-4">
            {{-- Penanda Tanggal --}}
            <div class="flex items-center gap-4">
                <div class="bg-gray-800 dark:bg-gray-100 text-white dark:text-gray-900 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest shadow-sm">
                    {{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }}
                </div>
                <div class="h-px flex-1 bg-gray-200 dark:bg-gray-800"></div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-sm overflow-hidden shadow-sm">
                <table class="w-full text-sm text-left border-separate border-spacing-0">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-gray-800/50 text-[10px] font-black uppercase tracking-widest text-gray-500">
                            <th class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 w-28">Waktu</th>
                            <th class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">Jenis Kayu</th>
                            <th class="px-4 py-4 border-b border-gray-100 dark:border-gray-700 text-center w-20">Panjang</th>
                            <th class="px-4 py-4 border-b border-gray-100 dark:border-gray-700 text-center w-30">Diameter</th>
                            <th class="px-4 py-4 border-b border-gray-100 dark:border-gray-700 text-center w-24">Grade</th>
                            <th class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 text-right">Harga Sebelum</th>
                            <th class="px-4 py-4 border-b border-gray-100 dark:border-gray-700 text-center w-12"></th>
                            <th class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 text-right">Harga Sesudah</th>
                            <th class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">Petugas / Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                        @foreach($items as $log)
                        @php
                        // Mengambil data master melalui relasi
                        $master = $log->hargaKayu;
                        $jenis = $master?->jenisKayu;
                        @endphp
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                            {{-- WAKTU --}}
                            <td class="px-6 py-5 font-mono text-xs text-gray-400">
                                {{ $log->created_at->format('H:i:s') }}
                            </td>

                            {{-- JENIS KAYU --}}
                            <td class="px-6 py-5">
                                <span class="font-black text-gray-800 dark:text-gray-200 uppercase tracking-tight">
                                    {{ $jenis?->nama_kayu ?? 'N/A' }}
                                </span>
                            </td>

                            {{-- PANJANG --}}
                            <td class="px-4 py-5 text-center">
                                <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-bold text-gray-600 dark:text-gray-400">
                                    {{ $master?->panjang ?? '-' }} cm
                                </span>
                            </td>

                            {{-- DIAMETER --}}
                            <td class="px-4 py-5 text-center font-mono text-xs text-gray-500">
                                {{ $master?->diameter_terkecil ?? '0' }} - {{ $master?->diameter_terbesar ?? '0' }}
                            </td>

                            {{-- GRADE --}}
                            <td class="px-4 py-5 text-center">
                                @if($master)
                                <span @class([ 'px-2 py-0.5 rounded text-[10px] font-black uppercase' , 'bg-blue-50 text-blue-600'=> $master->grade == 1,
                                    'bg-amber-50 text-amber-600' => $master->grade != 1,
                                    ])>
                                    {{ $master->grade == 1 ? 'Grade A' : 'Grade B' }}
                                </span>
                                @else
                                <span class="text-gray-300">-</span>
                                @endif
                            </td>

                            {{-- HARGA LAMA --}}
                            <td class="px-6 py-5 text-right font-medium text-gray-400 tabular-nums">
                                Rp {{ number_format($log->harga_lama, 0, ',', '.') }}
                            </td>

                            {{-- INDIKATOR --}}
                            <td class="px-2 py-5 text-center">
                                @if($log->aksi === 'Persetujuan Harga')
                                <svg class="w-4 h-4 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                                </svg>
                                @else
                                <svg class="w-4 h-4 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                @endif
                            </td>

                            {{-- HARGA BARU --}}
                            <td class="px-6 py-5 text-right font-black text-gray-900 dark:text-white tabular-nums text-base">
                                Rp {{ number_format($log->harga_baru, 0, ',', '.') }}
                            </td>

                            {{-- AUDIT INFO --}}
                            <td class="px-6 py-5 border-l border-gray-50 dark:border-gray-800">
                                <div class="flex flex-col gap-1">
                                    <span @class([ 'text-[9px] font-black px-2 py-0.5 rounded-sm uppercase inline-block self-start' , 'bg-green-100 text-green-700'=> $log->aksi === 'Persetujuan Harga',
                                        'bg-red-100 text-red-700' => $log->aksi === 'Penolakan Harga',
                                        ])>
                                        {{ $log->aksi }}
                                    </span>
                                    <span class="text-[11px] font-bold text-gray-700 dark:text-gray-300 uppercase tracking-tighter">
                                        PIC: {{ $log->petugas }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @empty
        <div class="p-20 text-center border-2 border-dashed border-gray-200 dark:border-gray-800 rounded">
            <span class="text-xs font-black uppercase tracking-[0.3em] text-gray-400 dark:text-gray-600">Belum ada riwayat aktivitas harga</span>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>