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

    {{-- Cards Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
        @forelse($this->targets as $record)
            @php
                $mesin = $record->mesin?->nama_mesin ?? '-';
                $ukuran = $record->ukuranModel?->dimensi ?? '-';
                $kayu = $record->jenisKayu?->kode_kayu ?? $record->jenisKayu?->nama_kayu ?? '-';
                $grade = $record->grade ? 'KW ' . $record->grade : null;
                $target = rtrim(rtrim(number_format($record->target, 4, ',', '.'), '0'), ',');
                if (str_ends_with($target, ',')) $target = rtrim($target, ',');
            @endphp
            <div class="bg-white dark:bg-gray-900 rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden flex flex-col h-full hover:shadow-sm transition-shadow duration-200">
                <div class="p-3 flex flex-col gap-2">
                    {{-- Baris 1: Mesin & Tombol Edit --}}
                    <div class="flex justify-between items-start gap-2">
                        <h3 class="font-semibold text-sm text-gray-950 dark:text-white truncate" title="{{ $mesin }}">
                            {{ $mesin }}
                        </h3>
                        <a href="{{ route('filament.admin.resources.targets.edit', ['record' => $record]) }}" class="text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors shrink-0">
                            <x-heroicon-m-pencil-square class="w-4 h-4" />
                        </a>
                    </div>

                    {{-- Baris 2: Detail (Ukuran, Kayu, Grade) --}}
                    <div class="flex flex-wrap items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-bold bg-gray-100 dark:bg-white/5 px-2 py-0.5 rounded text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-white/10">
                            {{ $ukuran }}
                        </span>
                        <span class="text-gray-300 dark:text-gray-600">&bull;</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $kayu }}</span>
                        @if($grade)
                            <span class="text-gray-300 dark:text-gray-600">&bull;</span>
                            <span class="text-info-600 dark:text-info-400 font-bold">{{ $grade }}</span>
                        @endif
                    </div>

                    {{-- Baris 3: Target Besar & Info Pekerja --}}
                    <div class="mt-1 flex items-end justify-between border-t border-gray-100 dark:border-white/5 pt-2">
                        <div>
                            <div class="text-[10px] uppercase font-semibold text-gray-400 dark:text-gray-500 tracking-wider">Target</div>
                            <div class="text-lg font-bold text-success-600 dark:text-success-400 leading-none mt-0.5">
                                {{ $target }}
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-300 font-semibold">
                            <div class="flex items-center gap-1" title="Jumlah Orang">
                                <x-heroicon-m-users class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                <span>{{ $record->orang ?? 0 }} Org</span>
                            </div>
                            <div class="flex items-center gap-1" title="Jam Kerja">
                                <x-heroicon-m-clock class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                <span>{{ $record->jam ?? 0 }} Jam</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 flex flex-col items-center justify-center text-gray-400 bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <x-heroicon-o-x-circle class="w-12 h-12 mb-3 text-gray-300 dark:text-gray-600" />
                <p class="text-sm font-medium">Tidak ada target yang sesuai kriteria pencarian.</p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
