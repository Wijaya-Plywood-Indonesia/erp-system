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
                            {{-- Lebar kolom disesuaikan untuk menampung format tanggal & jam --}}
                            <th class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 w-40">Waktu</th>
                            <th class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">Jenis Kayu / Detail Ukuran</th>
                            <th class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 text-right">Harga Sebelum</th>
                            <th class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 text-center w-16"></th>
                            <th class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 text-right">Harga Sesudah</th>
                            <th class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">Petugas / Approval</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                        @foreach($items as $record)
                        @php
                        $isPending = ($record->harga_baru > 0);
                        $isDisetujui = ($record->status === 'disetujui');
                        $isDitolak = ($record->status === 'ditolak');
                        @endphp
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                            {{-- PENYELARASAN: Menambahkan Tanggal pada Kolom Waktu --}}
                            <td class="px-6 py-5 font-mono text-xs text-gray-400">
                                {{ $record->updated_at->format('d/m/y H:i') }}
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-black text-gray-800 dark:text-gray-200 uppercase tracking-tight">
                                        {{ $record->jenisKayu?->nama_kayu ?? 'N/A' }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 uppercase">
                                            Grade {{ $record->grade == 1 ? 'A' : 'B' }}
                                        </span>
                                        <span class="text-[10px] text-gray-400 font-medium">
                                            P:{{ $record->panjang }} | D:{{ $record->diameter_terkecil }}-{{ $record->diameter_terbesar }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- KOLOM HARGA SEBELUM --}}
                            <td class="px-6 py-5 text-right font-medium text-gray-400 tabular-nums">
                                @if($isPending)
                                Rp {{ number_format($record->harga_beli, 0, ',', '.') }}
                                @else
                                <span class="text-[10px] italic opacity-50">Data Historis*</span>
                                @endif
                            </td>

                            {{-- INDIKATOR VISUAL --}}
                            <td class="px-2 py-5 text-center">
                                @if($isPending)
                                <svg class="w-4 h-4 mx-auto text-amber-500 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                                </svg>
                                @elseif($isDisetujui)
                                <svg class="w-5 h-5 mx-auto text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                @else
                                <svg class="w-5 h-5 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                @endif
                            </td>

                            {{-- KOLOM HARGA SESUDAH --}}
                            <td class="px-6 py-5 text-right font-black text-gray-900 dark:text-white tabular-nums text-base">
                                @if($isPending)
                                <span class="text-amber-500">Rp {{ number_format($record->harga_baru, 0, ',', '.') }}</span>
                                @else
                                Rp {{ number_format($record->harga_beli, 0, ',', '.') }}
                                @endif
                            </td>

                            {{-- AUDIT INFO --}}
                            <td class="px-6 py-5 border-l border-gray-50 dark:border-gray-800">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-1.5 h-1.5 rounded-full bg-blue-500"></div>
                                        <span class="text-[11px] font-black text-gray-700 dark:text-gray-300 uppercase">
                                            {{ $record->updater?->name ?? 'SYSTEM' }}
                                        </span>
                                    </div>

                                    @if($isDisetujui && $record->approver)
                                    <span class="text-[9px] font-bold text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 px-2 py-0.5 rounded-sm uppercase inline-block self-start">
                                        ✓ Approved by: {{ $record->approver->name }}
                                    </span>
                                    @elseif($isDitolak)
                                    <span class="text-[9px] font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-sm uppercase inline-block self-start">
                                        ✕ Rejected by: {{ $record->approver?->name ?? 'Admin' }}
                                    </span>
                                    @elseif($isPending)
                                    <span class="text-[9px] font-black text-amber-600 bg-amber-50 px-2 py-0.5 rounded-sm uppercase animate-pulse inline-block self-start">
                                        ⌚ Menunggu Approval
                                    </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(!$isPending)
            <p class="text-[9px] text-gray-400 italic px-2">* Catatan: Pada sistem tanpa tabel log, harga sebelum untuk data yang sudah final hanya perkiraan.</p>
            @endif
        </div>
        @empty
        <div class="p-20 text-center border-2 border-dashed border-gray-200 dark:border-gray-800 rounded">
            <span class="text-xs font-black uppercase tracking-[0.3em] text-gray-400 dark:text-gray-600">Belum ada riwayat aktivitas harga</span>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>