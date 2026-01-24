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
        {{-- Menambahkan mb-14 untuk jarak antar kotak pegawai agar tidak dempet --}}
        <div class="mb-14 border border-gray-700 rounded-lg overflow-hidden bg-gray-900 text-white shadow-2xl">

            {{-- Header Identitas: Nama di tengah dan besar, Jam di kiri/kanan --}}
            <div class="p-4 bg-gray-800 border-b border-gray-700">
                <div class="flex justify-between items-center">
                    {{-- Jam Masuk --}}
                    <div class="flex flex-col items-start">
                        <span class="text-[10px] text-gray-500 uppercase tracking-tighter">Jam Masuk</span>
                        <span class="text-xs font-bold text-green-400">{{ $pekerja['jam_masuk'] }}</span>
                    </div>

                    {{-- Nama Pekerja: Tengah & Besar --}}
                    <div class="text-center">
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-orange-500 mb-1">
                            LAPORAN POT SIKU - {{ $data['tanggal'] }}
                        </h3>
                        <h2 class="text-xl font-black uppercase tracking-tight text-white">
                            {{ $pekerja['kode_pegawai'] }} - {{ $pekerja['nama_pegawai'] }}
                        </h2>
                    </div>

                    {{-- Jam Pulang --}}
                    <div class="flex flex-col items-end">
                        <span class="text-[10px] text-gray-500 uppercase tracking-tighter">Jam Pulang</span>
                        <span class="text-xs font-bold text-red-400">{{ $pekerja['jam_pulang'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Tabel Detail --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-[11px] uppercase bg-gray-800 text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="px-6 py-3 border-r border-gray-700 text-center">Jenis Kayu</th>
                            <th class="px-6 py-3 border-r border-gray-700 text-center">Ukuran</th>
                            <th class="px-6 py-3 border-r border-gray-700 text-center">Kw</th>
                            <th class="px-6 py-3 border-r border-gray-700 text-center text-white">Hasil/Tinggi</th>
                            <th class="px-6 py-3 text-center text-red-400">Potongan Target</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pekerja['detail_barang'] as $index => $row)
                        <tr class="border-b border-gray-800 hover:bg-gray-800/50">
                            <td class="px-6 py-4 border-r border-gray-800 font-medium text-center uppercase">{{
                                $row['jenis_kayu'] }}</td>
                            <td class="px-6 py-4 border-r border-gray-800 text-center">{{ $row['ukuran'] }}</td>
                            <td class="px-6 py-4 border-r border-gray-800 text-center uppercase">{{ $row['kw'] }}</td>
                            <td class="px-6 py-4 border-r border-gray-800 text-center font-bold text-green-400">{{
                                $row['tinggi'] }}</td>

                            @if($loop->first)
                            <td rowspan="{{ count($pekerja['detail_barang']) }}"
                                class="px-6 py-4 text-center align-middle bg-red-950/10">
                                <div class="text-xl font-black text-red-500">
                                    Rp {{ number_format($pekerja['potongan_target'], 0, ',', '.') }}
                                </div>
                                <div class="text-[9px] text-gray-500 mt-1 uppercase">Total Potongan</div>
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                    {{-- Footer Summary --}}
                    <tfoot class="bg-gray-800/80 text-[10px] font-bold uppercase tracking-tighter">
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center border-t border-gray-700 text-gray-400">
                                <span class="mx-2">Target: <strong class="text-white">{{ $data['target_harian']
                                        }}</strong></span>
                                <span class="mx-1 text-gray-600">|</span>
                                <span class="mx-2">Jam Kerja: <strong class="text-white">{{ $data['jam_kerja']
                                        }}</strong></span>
                                <span class="mx-1 text-gray-600">|</span>
                                <span class="mx-2">Hasil: <strong class="text-green-400">{{ $pekerja['hasil']
                                        }}</strong></span>
                                <span class="mx-1 text-gray-600">|</span>
                                <span class="mx-2">Selisih: <strong class="text-red-500">{{ $pekerja['selisih']
                                        }}</strong></span>
                                <span class="mx-1 text-gray-600">|</span>
                                <span class="mx-2">Ijin: <strong class="text-yellow-500">{{ $pekerja['ijin']
                                        }}</strong></span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Kendala & Keterangan --}}
            <div class="p-4 bg-gray-800/30 border-t border-gray-700 text-[11px] grid grid-cols-2 gap-4">
                <div>
                    <span class="text-gray-500 font-bold uppercase">Kendala:</span>
                    <span class="text-yellow-500 ml-2 font-semibold italic">{{ $data['kendala'] }}</span>
                </div>
                <div class="text-right">
                    <span class="text-gray-500 font-bold uppercase">Keterangan:</span>
                    <span class="text-gray-300 ml-2 italic">{{ $pekerja['ket'] }}</span>
                </div>
            </div>
        </div>
        @endforeach
        @empty
        <div class="p-16 text-center bg-gray-800 rounded-xl border border-dashed border-gray-600 shadow-inner">
            <p class="text-gray-500 italic text-lg">Data laporan pot siku tidak tersedia untuk tanggal ini.</p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>