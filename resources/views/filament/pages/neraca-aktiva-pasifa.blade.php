<x-filament-panels::page>

    <div class="p-6 rounded-xl shadow bg-white dark:bg-gray-800 border dark:border-gray-700">

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

            <div>
                <label class="text-sm font-medium">Dari Bulan</label>
                <input type="month" max="{{ now()->format('Y-m') }}" wire:model="from_month"
                    class="w-full mt-1 rounded-lg border-gray-300">
            </div>

            <div>
                <label class="text-sm font-medium">Sampai Bulan</label>
                <input type="month" max="{{ now()->format('Y-m') }}" wire:model="to_month"
                    class="w-full mt-1 rounded-lg border-gray-300">
            </div>

            <div class="flex items-end">
                <x-filament::button wire:click="generate" class="w-full">
                    Generate
                </x-filament::button>
            </div>

            <div class="flex items-end">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" wire:model="hideZero">
                    <span>Sembunyikan saldo 0</span>
                </label>
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