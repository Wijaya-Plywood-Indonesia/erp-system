<div class="space-y-4" x-data="{ openConfirmModal: false }">

    {{-- HEADER BAR DENGAN TOMBOL SINKRON --}}
    <div class="bg-zinc-800 p-3 rounded-t-sm text-white flex justify-between items-center shadow border-b border-zinc-700">
        <div class="flex items-center gap-3">
            <h2 class="text-sm font-bold uppercase tracking-wider">
                PREVIEW LAPORAN ABSENSI & SINKRONISASI FINGER
            </h2>
            <div class="text-xs font-mono bg-zinc-700 px-2 py-0.5 rounded border border-zinc-600">
                {{ count($listAbsensi) }} DATA PEGAWAI
            </div>
        </div>

        {{-- TOMBOL TRIGER MODAL KONFIRMASI --}}
        <button
            type="button"
            @click="openConfirmModal = true"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-xs font-semibold rounded shadow transition duration-150 ease-in-out cursor-pointer">
            <x-heroicon-o-arrow-path-rounded-square class="w-4 h-4" />
            <span>Sinkronkan Data Ini</span>
        </button>
    </div>

    {{-- TABEL LAPORAN UTAMA --}}
    <div class="bg-white dark:bg-zinc-900 rounded-b-sm shadow-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="p-0 overflow-x-auto max-h-[55vh]">
            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-zinc-700 text-white text-[10px] uppercase tracking-wider">
                        <th class="p-3 text-center border-r border-zinc-600 w-16">Kodep</th>
                        <th class="p-3 text-left border-r border-zinc-600">Nama Pegawai</th>
                        <th class="p-2 text-left border-r border-zinc-600">Finger Masuk</th>
                        <th class="p-2 text-left border-r border-zinc-600">Finger Pulang</th>
                        <th class="p-2 text-left border-r border-zinc-600">Masuk</th>
                        <th class="p-2 text-left border-r border-zinc-600">Pulang</th>
                        <th class="p-3 text-left border-r border-zinc-600">Hasil / Divisi</th>
                        <th class="p-3 text-center border-r border-zinc-600 w-12">Ijin</th>
                        <th class="p-3 text-left">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($listAbsensi as $index => $row)
                    <tr class="{{ $index % 2 === 0 ? 'bg-white dark:bg-zinc-900' : 'bg-zinc-50 dark:bg-zinc-800/50' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition duration-75">
                        <td class="p-2 text-center text-xs font-mono border-r border-zinc-300 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400">
                            {{ $row['kodep'] }}
                        </td>
                        <td class="p-2 text-left text-xs font-semibold border-r border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100">
                            {{ $row['nama'] }}
                        </td>
                        <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-500">
                            {{ $row['f_masuk'] ?? '-' }}
                        </td>
                        <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-500">
                            {{ $row['f_pulang'] ?? '-' }}
                        </td>
                        <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-500">
                            {{ $row['masuk'] }}
                        </td>
                        <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-500">
                            {{ $row['pulang'] }}
                        </td>
                        <td class="p-2 text-left text-xs font-medium border-r border-zinc-300 dark:border-zinc-700">
                            <div class="flex flex-wrap gap-1.5">
                                @php
                                $divisiList = is_array($row['hasil']) ? $row['hasil'] : explode(' || ', $row['hasil']);
                                $isNightShift = false;
                                if (isset($row['f_masuk'], $row['f_pulang']) && $row['f_masuk'] !== '-' && $row['f_pulang'] !== '-') {
                                $isNightShift = strtotime($row['f_masuk']) > strtotime($row['f_pulang']);
                                }
                                @endphp

                                @foreach($divisiList as $divisi)
                                @php
                                $divisi = strtoupper(trim($divisi));
                                $isMalam = $isNightShift || str_contains($divisi, 'MALAM');
                                $isPagi = (!$isMalam) && ($row['f_masuk'] !== '-' && $row['f_pulang'] !== '-');
                                @endphp

                                @if($divisi === '-' || empty($divisi))
                                <span class="text-zinc-400 font-normal">-</span>
                                @elseif(str_contains($divisi, 'LAIN-LAIN'))
                                <div class="flex items-center gap-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300 ring-1 ring-amber-500/30">
                                        LAIN-LAIN
                                    </span>
                                    @php
                                    $detailLain = trim(str_replace(['LAIN-LAIN', ':', '-'], '', $divisi));
                                    @endphp
                                    @if(!empty($detailLain))
                                    <span class="text-[10px] text-zinc-500 font-medium italic">
                                        {{ $detailLain }}
                                    </span>
                                    @endif
                                </div>
                                @elseif(str_contains($divisi, 'ROTARY'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-orange-100 text-orange-800 ring-1 ring-orange-500/30">ROTARY</span>
                                @elseif(str_contains($divisi, 'DRYER'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold {{ $isMalam ? 'bg-indigo-100 text-indigo-800 ring-1 ring-indigo-500/30' : 'bg-green-100 text-green-800' }} border border-current uppercase">
                                    DRYER {{ $isMalam ? 'MALAM' : ($isPagi ? 'PAGI' : '') }}
                                </span>
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
                                @elseif(str_contains($divisi, 'DEMPUL'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-indigo-100 text-indigo-800 ring-1 ring-indigo-500/30">DEMPUL</span>
                                @elseif(str_contains($divisi, 'GRAJI TRIPLEK'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-sky-100 text-sky-800 ring-1 ring-sky-500/30 uppercase">GRAJI TRIPLEK</span>
                                @elseif(str_contains($divisi, 'TEMBEL TRIPLEK'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800 ring-1 ring-emerald-500/30 uppercase">TEMBEL TRIPLEK</span>
                                @elseif(str_contains($divisi, 'NYUSUP'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-lime-100 text-lime-800 ring-1 ring-lime-500/30 uppercase">NYUSUP</span>
                                @elseif(str_contains($divisi, 'SANDING'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold {{ $isMalam ? 'bg-indigo-100 text-indigo-800 ring-1 ring-indigo-500/30' : 'bg-teal-100 text-teal-800 ring-1 ring-teal-500/30' }} uppercase">
                                    SANDING {{ $isMalam ? 'MALAM' : ($isPagi ? 'PAGI' : '') }}
                                </span>
                                @elseif(str_contains($divisi, 'PILIH PLYWOOD'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-800 ring-1 ring-rose-500/30 uppercase">PILIH PLYWOOD</span>
                                @elseif(str_contains($divisi, 'HOT PRESS'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold {{ $isMalam ? 'bg-indigo-100 text-indigo-800 ring-1 ring-indigo-500/30' : 'bg-red-100 text-red-800 ring-1 ring-red-500/30' }} uppercase">
                                    HOT PRESS {{ $isMalam ? 'MALAM' : ($isPagi ? 'PAGI' : '') }}
                                </span>
                                @elseif(str_contains($divisi, 'POT SIKU'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-purple-100 text-purple-800 ring-1 ring-purple-500/30 uppercase">POT SIKU</span>
                                @elseif(str_contains($divisi, 'POT JELEK'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-800 ring-1 ring-rose-500/30 uppercase">POT JELEK</span>
                                @elseif(str_contains($divisi, 'TURUN KAYU'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-yellow-100 text-amber-800 dark:bg-yellow-900 dark:text-yellow-300 ring-1 ring-yellow-500/30 uppercase">TURUN KAYU</span>
                                @elseif(str_contains($divisi, 'PILIH VENEER'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-violet-100 text-violet-800 ring-1 ring-violet-500/30 uppercase">PILIH VENEER</span>
                                @elseif(str_contains($divisi, 'GUELLOTINE'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-lime-100 text-lime-800 ring-1 ring-lime-500/30 uppercase">GUELLOTINE</span>
                                @elseif(str_contains($divisi, 'GRAJI BALKEN'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-sky-100 text-sky-800 ring-1 ring-sky-500/30 uppercase">GRAJI BALKEN</span>
                                @elseif(str_contains($divisi, 'GRAJI STIK'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-indigo-100 text-indigo-800 ring-1 ring-indigo-500/30 uppercase">GRAJI STIK</span>
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
                            {{ $row['ijin'] }}
                        </td>
                        <td class="p-2 text-left text-[10px] italic text-zinc-600 dark:text-zinc-400">
                            {{ $row['keterangan'] }}
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
            </table>
        </div>
    </div>

    {{-- TABEL UNREGISTERED --}}
    @if(!empty($listUnregistered))
    <div class="mt-6">
        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-3 text-white flex justify-between items-center">
                <h2 class="text-sm font-bold uppercase tracking-wider">LOG MESIN (TIDAK TERDAFTAR)</h2>
                <div class="text-xs font-mono bg-zinc-700 px-2 py-1 rounded border border-zinc-600">
                    {{ count($listUnregistered) }} DATA TIDAK DIKENAL
                </div>
            </div>

            <div class="p-0 overflow-x-auto max-h-[30vh]">
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
                            <td class="p-2 text-center font-mono font-bold text-zinc-600 dark:text-zinc-400 border-r border-zinc-300 dark:border-zinc-700">
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
    @endif

    {{-- 🌟 MODAL BOX VALIDASI KONFIRMASI SINKRONISASI --}}
    <div
        x-show="openConfirmModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95">

        <div
            x-data="{ agreed: false }"
            x-init="$watch('openConfirmModal', value => { if(value) agreed = false })"
            @click.away="openConfirmModal = false"
            class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">

            {{-- HEADER MODAL --}}
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400 rounded-full">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                        Konfirmasi Sinkronisasi Data
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Proses ini akan mencocokkan jam finger dengan laporan absensi.
                    </p>
                </div>
            </div>

            <div class="bg-zinc-50 dark:bg-zinc-800/60 p-3 rounded border border-zinc-200 dark:border-zinc-700 text-xs text-zinc-600 dark:text-zinc-300">
                Apakah Anda yakin ingin melanjutkan proses sinkronisasi untuk seluruh data pegawai ini?
            </div>

            {{-- CHECKBOX PERSETUJUAN --}}
            <div class="flex items-start gap-2.5 pt-1">
                <input
                    type="checkbox"
                    id="check-persetujuan"
                    x-model="agreed"
                    class="mt-0.5 rounded border-zinc-300 text-emerald-600 shadow-sm focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-800 dark:focus:ring-emerald-600 dark:focus:ring-offset-zinc-900 cursor-pointer">
                <label for="check-persetujuan" class="text-xs font-medium text-zinc-700 dark:text-zinc-300 cursor-pointer select-none">
                    Saya telah memeriksa data preview dan menyetujui proses sinkronisasi ini.
                </label>
            </div>

            {{-- AREA TOMBOL AKSI --}}
            <div class="flex justify-end items-center gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                {{-- Tombol Batal --}}
                <button
                    type="button"
                    @click="openConfirmModal = false"
                    class="px-4 py-2 bg-zinc-200 hover:bg-zinc-300 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 text-xs font-semibold rounded transition">
                    Batal
                </button>

                {{-- 🌟 TOMBOL EKSEKUSI: HANYA MUNCUL JIKA CHECKBOX DICENTANG --}}
                <button
                    x-show="agreed"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-90"
                    x-transition:enter-end="opacity-100 scale-100"
                    type="button"
                    wire:click="sinkronKanData"
                    @click="openConfirmModal = false"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-xs font-semibold rounded shadow-md transition duration-150 cursor-pointer">
                    <x-heroicon-o-check-circle class="w-4 h-4" />
                    <span>Ya, Saya Setujui & Sinkronkan</span>
                </button>
            </div>
        </div>
    </div>

</div>