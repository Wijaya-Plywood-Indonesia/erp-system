<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-700">
        {{ $this->form }}
    </div>

    @if($isLoading)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75">
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Sedang memproses data absensi...</span>
        </div>
    </div>
    @endif

    <div class="mt-6">
        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                <h2 class="text-lg font-bold text-center uppercase tracking-wider">
                    LAPORAN ABSENSI & SINKRONISASI FINGER
                </h2>
                <div class="text-xs font-mono bg-zinc-700 px-2 py-1 rounded border border-zinc-600">
                    {{ count($listAbsensi) }} DATA PEGAWAI
                </div>
            </div>

            <div class="p-0">
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[1000px]"> {{-- Lebar kontainer disesuaikan --}}
                        <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                            <thead>
                                <tr class="bg-zinc-700 text-white text-[10px] uppercase tracking-wider">
                                    <th class="p-3 text-center border-r border-zinc-600 w-16">Kodep</th>
                                    <th class="p-3 text-left border-r border-zinc-600">Nama Pegawai</th>

                                    {{-- KOLOM JAM MANUAL --}}
                                    <th class="p-2 text-left border-r border-zinc-600">Masuk</th>
                                    <th class="p-2 text-left border-r border-zinc-600">Pulang</th>

                                    {{-- KOLOM JAM MESIN FINGER --}}
                                    <th class="p-2 text-left border-r border-zinc-600">Finger Masuk</th>
                                    <th class="p-2 text-left border-r border-zinc-600">Finger Pulang</th>

                                    <th class="p-3 text-left border-r border-zinc-600">Hasil / Divisi</th>
                                    <th class="p-3 text-center border-r border-zinc-600 w-12">Ijin</th>
                                    <th class="p-3 text-left">Keterangan</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($listAbsensi as $index => $row)
                                <tr class="{{ $index % 2 === 0 ? 'bg-white dark:bg-zinc-900' : 'bg-zinc-50 dark:bg-zinc-800/50' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition duration-75">

                                    <td class="p-2 text-center text-xs font-mono border-r border-zinc-300 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400">
                                        {{ $row["kodep"] }}
                                    </td>

                                    <td class="p-2 text-left text-xs font-semibold border-r border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100">
                                        {{ $row["nama"] }}
                                    </td>

                                    {{-- DATA MANUAL --}}
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-500">
                                        {{ $row["masuk"] }}
                                    </td>

                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-500">
                                        {{ $row["pulang"] }}
                                    </td>

                                    {{-- DATA MESIN FINGER (HIGHLIGHT BIRU) --}}
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-500">
                                        {{ $row["f_masuk"] ?? '-' }}
                                    </td>

                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-500">
                                        {{ $row["f_pulang"] ?? '-' }}
                                    </td>

                                    <td class="p-2 text-left text-xs font-medium border-r border-zinc-300 dark:border-zinc-700">
                                        <div class="flex flex-wrap gap-1.5">
                                            @php
                                            $divisiList = is_array($row['hasil']) ? $row['hasil'] : explode(', ', $row['hasil']);
                                            @endphp

                                            @foreach($divisiList as $divisi)
                                            @php $divisi = trim($divisi); @endphp
                                            @if($divisi === '-' || empty($divisi))
                                            <span class="text-zinc-400 font-normal">-</span>
                                            @elseif(str_contains($divisi, 'ROTARY'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-orange-100 text-orange-800 ring-1 ring-orange-500/30">ROTARY</span>
                                            @elseif(str_contains($divisi, 'DRYER'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-green-100 text-green-800 border border-current uppercase">DRYER</span>
                                            @elseif(str_contains($divisi, 'REPAIR'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 ring-1 ring-blue-500/30">REPAIR</span>
                                            @elseif(str_contains($divisi, 'SANDING JOINT'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-teal-100 text-teal-800 border border-teal-200 uppercase">SANDING JOIN</span>
                                            @elseif(str_contains($divisi, 'JOINT'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-cyan-100 text-cyan-800 border border-cyan-200 uppercase">JOIN</span>
                                            @elseif(str_contains($divisi, 'STIK'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-pink-100 text-pink-800 border border-pink-200 uppercase">STIK</span>
                                            @elseif(str_contains($divisi, 'KEDI') || str_contains($divisi, 'PUTTY'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-purple-100 text-purple-800 border border-purple-200 uppercase">KEDI</span>
                                            @elseif(str_contains($divisi, 'POT AFALAN'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-800 border border-rose-200 uppercase">POT AFALAN</span>
                                            @elseif(str_contains($divisi, 'LAIN-LAIN'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300 ring-1 ring-amber-500/30">LAIN-LAIN</span>
                                            @elseif(str_contains($divisi, 'DEMPUL'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-indigo-100 text-indigo-800 ring-1 ring-indigo-500/30">DEMPUL</span>
                                            @elseif(str_contains($divisi, 'GRAJI TRIPLEK'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-sky-100 text-sky-800 ring-1 ring-sky-500/30 uppercase">GRAJI TRIPLEK</span>
                                            @elseif(str_contains($divisi, 'NYUSUP'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-lime-100 text-lime-800 ring-1 ring-lime-500/30 uppercase">NYUSUP</span>
                                            @elseif(str_contains($divisi, 'SANDING'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-teal-100 text-teal-800 ring-1 ring-teal-500/30 uppercase">SANDING</span>
                                            @elseif(str_contains($divisi, 'PILIH PLYWOOD'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-800 ring-1 ring-rose-500/30 uppercase">PILIH PLYWOOD</span>
                                            @elseif(str_contains($divisi, 'HOT PRESS'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-red-100 text-red-800 ring-1 ring-red-500/30 uppercase">HOT PRESS</span>
                                            @elseif(str_contains($divisi, 'POT SIKU'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-purple-100 text-purple-800 ring-1 ring-purple-500/30 uppercase">POT SIKU</span>
                                            @elseif(str_contains($divisi, 'POT JELEK'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-800 ring-1 ring-rose-500/30 uppercase">POT JELEK</span>
                                            @elseif(str_contains($divisi, 'TURUN KAYU'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-yellow-100 text-amber-800 dark:bg-yellow-900 dark:text-yellow-300 ring-1 ring-yellow-500/30 uppercase">TURUN KAYU</span>
                                            @elseif(str_contains($divisi, 'Sync Error'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-red-600 text-white animate-pulse">KODE TIDAK TERDAFTAR</span>
                                            @elseif(str_contains($divisi, 'Finger tanpa produksi'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-blue-100 text-blue-800 border border-blue-300">HANYA FINGER</span>
                                            @else
                                            @php
                                            $divisiOnly = explode(' ', $divisi)[0];
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-zinc-100 text-zinc-800 border border-zinc-200 uppercase">{{ $divisiOnly }}</span>
                                            @endif
                                            @endforeach
                                        </div>
                                    </td>

                                    <td class="p-2 text-center text-xs font-bold border-r border-zinc-300 dark:border-zinc-700 text-yellow-600">
                                        {{ $row["ijin"] }}
                                    </td>

                                    <td class="p-2 text-left text-[10px] italic text-zinc-600 dark:text-zinc-400">
                                        {{ $row["keterangan"] }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="p-12 text-center text-zinc-500">
                                        <div class="flex flex-col items-center justify-center">
                                            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mb-2 opacity-50" />
                                            <p class="text-lg">Tidak ada data absensi untuk tanggal ini.</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>

                            @if(!empty($listAbsensi))
                            <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300">
                                <tr>
                                    <td colspan="9" class="p-3 text-center text-xs text-zinc-600 space-x-4">
                                        <span class="font-medium">Total Baris Laporan:</span>
                                        <strong class="text-zinc-900 dark:text-white text-sm">{{ count($listAbsensi) }}</strong>
                                    </td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(count($listUnregistered) > 0)
    <div class="mt-8">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-bold text-zinc-700 dark:text-zinc-300 uppercase flex items-center gap-2">
                <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-500" />
                Data ID Mesin Tidak Terdaftar
            </h3>
            <x-filament::button
                wire:click="syncKeWebsiteLain"
                wire:loading.attr="disabled"
                icon="heroicon-o-cloud-arrow-up"
                color="info"
                size="md">
                <span wire:loading.remove>Sinkron Wijaya</span>
                <span wire:loading>Sedang Mengirim...</span>
            </x-filament::button>
        </div>

        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                <h2 class="text-sm font-bold uppercase tracking-wider">LOG MESIN (TIDAK TERDAFTAR)</h2>
                <div class="text-xs font-mono bg-zinc-700 px-2 py-1 rounded border border-zinc-600">
                    {{ count($listUnregistered) }} DATA BERMASALAH
                </div>
            </div>

            <div class="p-0">
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                        <thead>
                            <tr class="bg-zinc-700 text-white text-[10px] uppercase tracking-wider">
                                <th class="p-3 text-center border-r border-zinc-600 w-32">ID Mesin</th>
                                <th class="p-3 text-left border-r border-zinc-600">Nama Pegawai</th>
                                <th class="p-3 text-center border-r border-zinc-600 w-40">Finger Masuk</th>
                                <th class="p-3 text-center border-r border-zinc-600 w-40">Finger Pulang</th>
                                <th class="p-3 text-left">Keterangan Sistem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($listUnregistered as $index => $unreg)
                            <tr class="{{ $index % 2 === 0 ? 'bg-white dark:bg-zinc-900' : 'bg-zinc-50 dark:bg-zinc-800/50' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition duration-75">
                                <td class="p-2 text-center font-mono font-bold text-zinc-100 border-r border-zinc-300 dark:border-zinc-700">
                                    {{ $unreg['kodep'] }}
                                </td>
                                <td class="p-2 text-center italic text-zinc-400 font-light border-r border-zinc-300 dark:border-zinc-700">
                                    (Kosong)
                                </td>
                                <td class="p-2 text-center font-mono border-r border-zinc-300 dark:border-zinc-700 text-zinc-500">
                                    {{ $unreg['f_masuk'] }}
                                </td>
                                <td class="p-2 text-center font-mono border-r border-zinc-300 dark:border-zinc-700 text-zinc-500">
                                    {{ $unreg['f_pulang'] }}
                                </td>
                                <td class="p-2 text-[10px] text-zinc-600 dark:text-zinc-400 italic">
                                    {{ $unreg['keterangan'] }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif
</x-filament-panels::page>