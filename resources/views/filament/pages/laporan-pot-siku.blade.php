<x-filament-panels::page>
    <div class="bg-white dark:bg-gray-900 p-4 rounded-lg shadow mb-6 text-black dark:text-white">
        {{ $this->form }}
    </div>

    <div wire:loading wire:target="loadAllData" class="w-full text-center py-4">
        <span class="text-gray-500 italic">Sedang memproses laporan perorangan...</span>
    </div>

    <div wire:loading.remove class="space-y-12">
        @forelse($dataSiku as $data)
        @foreach($data['pekerja_list'] as $pekerja)

        @php
            $target = $pekerja['target'] ?? 300;
            $hasil  = $pekerja['hasil'] ?? 0;
            $progress = $target > 0 ? min(100, round(($hasil / $target) * 100, 1)) : 0;
        @endphp

        <div class="mb-14 border border-gray-700 rounded-lg overflow-hidden bg-gray-900 text-white shadow-2xl">

            {{-- ================= HEADER ================= --}}
            <div class="p-4 bg-gray-800 border-b border-gray-700">
                <div class="flex justify-between items-center">
                    <div class="flex flex-col items-start">
                        <span class="text-[10px] text-gray-500 uppercase">Jam Masuk</span>
                        <span class="text-xs font-bold text-green-400">{{ $pekerja['jam_masuk'] }}</span>
                    </div>

                    <div class="text-center">
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-orange-500 mb-1">
                            LAPORAN POT SIKU - {{ $data['tanggal'] }}
                        </h3>
                        <div class="flex items-center justify-center gap-2">
    <h2 class="text-xl font-black uppercase text-white">
        {{ $pekerja['kode_pegawai'] }} - {{ $pekerja['nama_pegawai'] }}
    </h2>

    {{-- BADGE --}}
    @if($hasil >= $target)
        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-green-600/20 text-green-400 border border-green-600">
            ✔ Tercapai
        </span>
    @else
        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-red-600/20 text-red-400 border border-red-600">
            ✘ Belum
        </span>
    @endif
</div>
                    </div>

                    <div class="flex flex-col items-end">
                        <span class="text-[10px] text-gray-500 uppercase">Jam Pulang</span>
                        <span class="text-xs font-bold text-red-400">{{ $pekerja['jam_pulang'] }}</span>
                    </div>
                </div>
{{-- BADGE --}}
<div class="mt-1">
    @if($hasil >= $target)
        <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-green-600/20 text-green-400 border border-green-600">
            ✔ TER CAPAI
        </span>
    @else
        <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-red-600/20 text-red-400 border border-red-600">
            ✘ BELUM TERCAPAI
        </span>
    @endif
</div>
                {{-- ================= PROGRESS BAR ================= --}}
                <div class="mt-4">
                    <div class="flex justify-between text-[11px] mb-1">
                        <span class="text-gray-400">Progress Target ({{ $target }} cm)</span>
                        <span class="text-gray-300 font-bold">
                            {{ $hasil }} / {{ $target }} cm ({{ $progress }}%)
                        </span>
                    </div>

                    <div style="width:100%;height:10px;background:#2d2d2d;border-radius:999px;overflow:hidden;">
                        <div
                            style="
                                width: {{ $progress }}%;
                                height: 100%;
                                background: {{ $progress >= 100 ? '#22c55e' : ($progress >= 75 ? '#3b82f6' : '#f59e0b') }};
                                transition: width .4s ease;
                            ">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ================= TABEL DETAIL ================= --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-[11px] uppercase bg-gray-800 text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-center">Jenis Kayu</th>
                            <th class="px-6 py-3 text-center">Ukuran</th>
                            <th class="px-6 py-3 text-center">KW</th>
                            <th class="px-6 py-3 text-center text-green-400">Hasil</th>
                            <th class="px-6 py-3 text-center text-red-400">Potongan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pekerja['detail_barang'] as $row)
                        <tr class="border-b border-gray-800 hover:bg-gray-800/40">
                            <td class="px-6 py-3 text-center uppercase">{{ $row['jenis_kayu'] }}</td>
                            <td class="px-6 py-3 text-center">{{ $row['ukuran'] }}</td>
                            <td class="px-6 py-3 text-center uppercase">{{ $row['kw'] }}</td>
                            <td class="px-6 py-3 text-center font-bold text-green-400">{{ $row['tinggi'] }}</td>
                            <td class="px-6 py-3 text-center font-bold text-red-500">
                                Rp {{ number_format($pekerja['potongan_target'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>

                    {{-- ================= FOOTER ================= --}}
                    <tfoot class="bg-gray-800 text-[10px] uppercase">
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-gray-400">
                                Target: <strong class="text-white">{{ $target }}</strong> |
                                Hasil: <strong class="text-green-400">{{ $hasil }}</strong> |
                                Selisih: <strong class="text-red-500">{{ $pekerja['selisih'] }}</strong> |
                                Ijin: <strong class="text-yellow-500">{{ $pekerja['ijin'] }}</strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- ================= KENDALA ================= --}}
            <div class="p-4 bg-gray-800/30 border-t border-gray-700 text-[11px] grid grid-cols-2">
                <div>
                    <span class="text-gray-500 font-bold uppercase">Kendala:</span>
                    <span class="text-yellow-500 ml-2 italic">{{ $data['kendala'] }}</span>
                </div>
                <div class="text-right">
                    <span class="text-gray-500 font-bold uppercase">Ket:</span>
                    <span class="text-gray-300 ml-2 italic">{{ $pekerja['ket'] }}</span>
                </div>
            </div>
        </div>

        @endforeach
        @empty
        <div class="p-16 text-center bg-gray-800 rounded-xl border border-dashed border-gray-600">
            <p class="text-gray-500 italic text-lg">
                Data laporan pot siku tidak tersedia untuk tanggal ini.
            </p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>
