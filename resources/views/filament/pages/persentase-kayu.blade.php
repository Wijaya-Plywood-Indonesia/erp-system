<x-filament::page>
<style>
    [x-cloak] { display: none !important; }
</style>
<div x-data="{ 
    selected: [], 
    // Fungsi untuk buka semua: mengambil semua index dari data yang ada
    openAll(count) {
        this.selected = Array.from({length: count}, (_, i) => i);
    },
    // Fungsi untuk tutup semua
    closeAll() {
        this.selected = [];
    },
    // Fungsi toggle satu baris
    toggleRow(idx) {
        if (this.selected.includes(idx)) {
            this.selected = this.selected.filter(i => i !== idx);
        } else {
            this.selected.push(idx);
        }
    }
}" class="p-4 space-y-4">
    <div class="flex gap-2 mb-4">
        <button @click="openAll({{ count($full_data) }})" 
                type="button"
                class="px-3 py-2 text-xs font-bold rounded-lg shadow-sm transition
                    bg-primary-500 text-white hover:bg-primary-600 
                    dark:text-black
                    dark:bg-primary-500 dark:hover:bg-primary-400
                    ring-1 ring-primary-400 dark:ring-0">
            🔓 Buka Semua Baris
        </button>
        <button @click="closeAll()" 
                type="button"
                class="px-3 py-2 text-xs font-bold rounded-lg shadow-sm transition
                    bg-white text-gray-950 hover:bg-gray-50 dark:hover:bg-gray-900 ring-1 ring-gray-950/10
                    dark:bg-white/10 dark:text-white dark:ring-0">
            🔒 Tutup Semua Baris
        </button>
    </div>
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900 overflow-hidden">
        <table class="w-full text-left text-sm table-auto border-separate border-spacing-0">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-900 dark:bg-white/5 dark:border-white/10">
                    <th class="px-4 py-3 font-semibold">Tgl Masuk</th>
                    <th class="px-4 py-3 font-semibold">Lahan</th>
                    <th class="px-4 py-3 font-semibold text-center">Batang</th>
                    <th class="px-4 py-3 font-semibold">Kubikasi Kayu</th>
                    <th class="px-4 py-3 font-semibold text-green-600 dark:text-green-400">Poin</th>
                    <th class="px-4 py-3 font-semibold">Kubikasi Veneer</th>
                    <th class="px-4 py-3 font-semibold">Persentase (%)</th>
                    <th class="px-4 py-3 font-semibold text-green-600 dark:text-green-400">Veneer</th>
                    <th class="px-4 py-3 font-semibold text-green-600 dark:text-green-400">Veneer+Ongkos</th>
                    <th class="px-4 py-3 font-semibold text-green-600 dark:text-green-400">Veneer+Ongkos+Susut</th>
                </tr>
            </thead>
            
            <tbody>
                @forelse($full_data as $index => $data)
                    <tr @click="toggleRow({{ $index }})" 
                        class="cursor-pointer border-b border-gray-100 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5 transition-colors"
                        :class="selected.includes({{ $index }}) ? 'bg-gray-50 dark:bg-white/5' : ''">
                        <td class="px-4 py-4 whitespace-nowrap">{{ $data['tgl_masuk'] ?? '2026-02-19' }}</td>
                        <td class="px-4 py-4">{{ $data['lahan'] ?? 'Lahan Percobaan' }}</td>
                        <td class="px-4 py-4 text-center">{{ $data['total_batang'] ?? 0 }}</td>
                        <td class="px-4 py-4">{{ number_format($data['kubikasi_kayu'] ?? 1.5, 3) }} m³</td>
                        <td class="px-4 py-4 font-bold text-green-600 dark:text-green-400">{{ number_format($data['poin'] ?? 500) }}</td>
                        <td class="px-4 py-4">{{ number_format($data['kubikasi_veneer'] ?? 1.2, 3) }} m³</td>
                        <td class="px-4 py-4 font-medium">
                            @php
                                $kubikKayu = $data['kubikasi_kayu'] ?? 1;
                                $kubikVeneer = $data['kubikasi_veneer'] ?? 0;
                                $persentase = ($kubikVeneer / $kubikKayu) * 100;
                            @endphp
                            {{ round($persentase, 2) }}%
                        </td>
                        <td class="px-4 py-4 font-bold text-green-600 dark:text-green-400">Rp {{ number_format($data['harga_veneer'] ?? 800000) }}</td>
                        <td class="px-4 py-4 font-bold text-green-600 dark:text-green-400">Rp {{ number_format($data['harga_v_ongkos'] ?? 1200000) }}</td>
                        <td class="px-4 py-4 font-bold text-green-600 dark:text-green-400">Rp {{ number_format($data['harga_total'] ?? 1350000) }}</td>
                    </tr>

                    <tr x-show="selected.includes({{ $index }})" x-cloak x-transition>
                        <td colspan="10" class="bg-gray-50/50 p-4 dark:bg-white/5">
                            <div class="space-y-4" x-data="{ openMasuk: true, openKeluar: true }">
                                <div class="rounded-lg border border-gray-200 dark:border-white/10 overflow-hidden">
                                    <button @click="openMasuk = !openMasuk" 
                                                class="w-full flex justify-between items-center px-4 py-2 bg-white dark:bg-gray-800 font-bold text-sm">
                                            <span>📦 KAYU MASUK</span>
                                            <svg class="w-4 h-4 transition-transform" :class="openMasuk ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div x-show="openMasuk" class="p-2 overflow-x-auto">
                                        <table class="w-full text-xs text-left">
                                            <thead class="bg-gray-100 dark:bg-gray-700">
                                                <tr>
                                                    <th class="px-2 py-2">Tgl Masuk</th>
                                                    <th class="px-2 py-2">Seri</th>
                                                    <th class="px-2 py-2">Banyak</th>
                                                    <th class="px-2 py-2">Kubikasi</th>
                                                    <th class="px-2 py-2 text-green-600 dark:text-green-400">Poin</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($data['kayu_masuk_detail'] ?? [1] as $km)
                                                <tr class="border-b dark:border-white/5">
                                                    <td class="px-2 py-2">{{ $km['tgl'] ?? '2026-02-19' }}</td>
                                                    <td class="px-2 py-2">{{ $km['seri'] ?? 'SR-001' }}</td>
                                                    <td class="px-2 py-2">{{ $km['banyak'] ?? 10 }}</td>
                                                    <td class="px-2 py-2">{{ $km['kubikasi'] ?? 0.5 }}</td>
                                                    <td class="px-2 py-2 text-green-600 dark:text-green-400 font-bold">{{ $km['poin'] ?? 150 }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-200 dark:border-white/10 overflow-hidden">
                                    <button @click="openKeluar = !openKeluar" 
                                            class="w-full flex justify-between items-center px-4 py-2 bg-white dark:bg-gray-800 font-bold text-sm">
                                        <span>🪵KAYU KELUAR</span>
                                        <svg class="w-4 h-4 transition-transform" :class="openKeluar ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div x-show="openKeluar" class="p-2 overflow-x-auto">
                                        <table class="w-full text-xs text-left">
                                            <thead class="bg-gray-100 dark:bg-gray-700">
                                                <tr>
                                                    <th class="px-2 py-2 whitespace-nowrap">Tgl</th>
                                                    <th class="px-2 py-2">Mesin</th>
                                                    <th class="px-2 py-2 whitespace-nowrap">Jam Kerja</th>
                                                    <th class="px-2 py-2">Ukuran</th>
                                                    <th class="px-2 py-2">Banyak</th>
                                                    <th class="px-2 py-2">Kubikasi</th>
                                                    <th class="px-2 py-2">Pekerja</th>
                                                    <th class="px-2 py-2 text-green-600 dark:text-green-400">Ongkos</th>
                                                    <th class="px-2 py-2">Penyusutan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($data['kayu_keluar_detail'] ?? [1] as $kk)
                                                <tr class="border-b dark:border-white/5">
                                                    <td class="px-2 py-2 whitespace-nowrap">{{ $kk['tgl'] ?? '2026-02-19' }}</td>
                                                    <td class="px-2 py-2">{{ $kk['mesin'] ?? 'Rotary 01' }}</td>
                                                    <td class="px-2 py-2">{{ $kk['jam_kerja'] ?? '8 Jam' }}</td>
                                                    <td class="px-2 py-2 whitespace-nowrap">{{ $kk['ukuran'] ?? '1.2 x 2.4' }}</td>
                                                    <td class="px-2 py-2">{{ $kk['banyak'] ?? 50 }}</td>
                                                    <td class="px-2 py-2">{{ $kk['kubikasi'] ?? 0.8 }}</td>
                                                    <td class="px-2 py-2">{{ $kk['pekerja'] ?? 'Tim A' }}</td>
                                                    <td class="px-2 py-2 text-green-600 dark:text-green-400 font-bold">Rp {{ number_format($kk['ongkos'] ?? 50000) }}</td>
                                                    <td class="px-2 py-2">Rp {{ number_format($kk['penyusutan'] ?? 15000) }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            Data produksi belum tersedia.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $this->table }}
</x-filament::page>


{{-- <x-filament::page>
</x-filament::page> --}}
