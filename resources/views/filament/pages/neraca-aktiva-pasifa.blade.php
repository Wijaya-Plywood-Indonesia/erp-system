<x-filament-panels::page>

    <div class="p-6 rounded-xl shadow bg-white dark:bg-gray-800 border dark:border-gray-700">

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

            <div>
                <label class="text-sm font-medium">Mulai Bulan</label>
                <input type="month"
                       wire:model.defer="start_date"
                       class="w-full mt-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>

            <div>
                <label class="text-sm font-medium">Sampai Tanggal</label>
                <input type="date"
                       wire:model.defer="end_date"
                       class="w-full mt-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>

            <div>
                <label class="text-sm font-medium">Repeat Bulan</label>
                <select wire:model.defer="repeat"
                        class="w-full mt-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    @for($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}">{{ $i }} Bulan</option>
                    @endfor
                </select>
            </div>

            <div class="flex items-end">
                <x-filament::button wire:click="applyFilter" class="w-full">
                    Terapkan Filter
                </x-filament::button>
            </div>

        </div>
    </div>

    @foreach($results as $period)

        <div class="mt-10">

            <h2 class="text-xl font-bold mb-6">
                Neraca {{ $period['label'] }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                {{-- AKTIVA --}}
                <div class="p-6 rounded-xl shadow bg-white dark:bg-gray-800 border dark:border-gray-700">
                    <h3 class="text-lg font-bold mb-4 border-b pb-2">AKTIVA</h3>

                    @foreach($period['aktiva'] as $group)
                        @include('filament.pages.partials.neraca-tree', ['group' => $group, 'level' => 0])
                    @endforeach
                </div>

                {{-- PASIVA --}}
                <div class="p-6 rounded-xl shadow bg-white dark:bg-gray-800 border dark:border-gray-700">
                    <h3 class="text-lg font-bold mb-4 border-b pb-2">PASIVA</h3>

                    @foreach($period['pasiva'] as $group)
                        @include('filament.pages.partials.neraca-tree', ['group' => $group, 'level' => 0])
                    @endforeach
                </div>

            </div>

        </div>

    @endforeach

</x-filament-panels::page>