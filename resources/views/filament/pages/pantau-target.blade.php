<x-filament-panels::page>
    <div class="flex items-center justify-between bg-white dark:bg-gray-900 px-4 py-3 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        {{-- Mesin Filter --}}
        <div class="flex items-center gap-3">
            <x-heroicon-m-funnel class="w-5 h-5 text-gray-400" />
            <select wire:model.live="mesin_id" class="border-none bg-transparent dark:bg-gray-900 focus:ring-0 text-sm font-semibold text-gray-900 dark:text-white cursor-pointer px-2 py-1">
                <option value="" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Semua Mesin Produksi</option>
                @foreach($this->mesins as $m)
                    <option value="{{ $m->id }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">{{ $m->nama_mesin }}</option>
                @endforeach
            </select>
        </div>

        {{-- Search Input --}}
        <div class="relative w-64">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <x-heroicon-m-magnifying-glass class="w-4 h-4 text-gray-400" />
            </div>
            <input type="text" wire:model.live.debounce.300ms="search" class="bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 transition-colors" placeholder="Cari mesin, ukuran, jenis kayu...">
        </div>
    </div>

    {{-- Grouped by Mesin --}}
    <div class="space-y-6">
        @forelse($this->targets as $namaMesin => $groupedTargets)
            <div x-data="{ open: true }" class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                {{-- Group Header --}}
                <div @click="open = !open" class="flex items-center justify-between px-5 py-3.5 bg-gray-50/75 dark:bg-white/[0.03] border-b border-gray-100 dark:border-white/5 cursor-pointer select-none hover:bg-gray-100/70 dark:hover:bg-white/[0.05] transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="p-1.5 rounded-lg bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400">
                            <x-heroicon-m-cog-6-tooth class="w-5 h-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                {{ $namaMesin }}
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                    {{ $groupedTargets->count() }} Target
                                </span>
                            </h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-gray-400">
                        <span class="text-xs font-medium hidden sm:inline" x-text="open ? 'Sembunyikan' : 'Tampilkan'"></span>
                        <x-heroicon-m-chevron-down class="w-5 h-5 transform transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                    </div>
                </div>

                {{-- Table Inside Group --}}
                <div x-show="open" x-collapse>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300 divide-y divide-gray-200 dark:divide-white/5">
                            <thead class="bg-gray-50/50 dark:bg-white/[0.02] text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-5 py-3">Ukuran (P x L x T)</th>
                                    <th scope="col" class="px-5 py-3">Jenis Kayu</th>
                                    <th scope="col" class="px-5 py-3 text-center">Grade / KW</th>
                                    <th scope="col" class="px-5 py-3 text-right">Target</th>
                                    <th scope="col" class="px-5 py-3 text-center">Pekerja</th>
                                    <th scope="col" class="px-5 py-3 text-center">Jam Kerja</th>
                                    <th scope="col" class="px-5 py-3 text-center w-16">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5 bg-white dark:bg-gray-900">
                                @foreach($groupedTargets as $record)
                                    @php
                                        $ukuran = $record->ukuranModel?->dimensi ?? '-';
                                        $kayu = $record->jenisKayu?->nama_kayu ?? $record->jenisKayu?->kode_kayu ?? '-';
                                        $grade = $record->grade ? 'KW ' . $record->grade : '-';
                                        $target = rtrim(rtrim(number_format($record->target, 4, ',', '.'), '0'), ',');
                                        if (str_ends_with($target, ',')) $target = rtrim($target, ',');
                                    @endphp
                                    <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="px-5 py-3 font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                            <span class="bg-gray-100 dark:bg-white/5 px-2 py-0.5 rounded border border-gray-200 dark:border-white/10 font-mono text-xs">
                                                {{ $ukuran }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                            {{ $kayu }}
                                        </td>
                                        <td class="px-5 py-3 text-center whitespace-nowrap">
                                            @if($record->grade)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-info-50 dark:bg-info-950/40 text-info-700 dark:text-info-400 border border-info-200 dark:border-info-800/40">
                                                    {{ $grade }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap">
                                            <span class="text-base font-bold text-success-600 dark:text-success-400">
                                                {{ $target }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-center whitespace-nowrap font-medium text-gray-700 dark:text-gray-300">
                                            <div class="inline-flex items-center gap-1">
                                                <x-heroicon-m-users class="w-4 h-4 text-gray-400" />
                                                <span>{{ $record->orang ?? 0 }} Org</span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-center whitespace-nowrap font-medium text-gray-700 dark:text-gray-300">
                                            <div class="inline-flex items-center gap-1">
                                                <x-heroicon-m-clock class="w-4 h-4 text-gray-400" />
                                                <span>{{ $record->jam ?? 0 }} Jam</span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-center whitespace-nowrap">
                                            <a href="{{ route('filament.admin.resources.targets.edit', ['record' => $record]) }}" class="inline-flex items-center justify-center p-1 rounded-lg text-gray-400 hover:text-primary-600 hover:bg-gray-100 dark:hover:bg-white/5 transition-colors" title="Edit Target">
                                                <x-heroicon-m-pencil-square class="w-4 h-4" />
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div class="py-12 flex flex-col items-center justify-center text-gray-400 bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <x-heroicon-o-x-circle class="w-12 h-12 mb-3 text-gray-300 dark:text-gray-600" />
                <p class="text-sm font-medium">Tidak ada target yang sesuai kriteria pencarian.</p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
