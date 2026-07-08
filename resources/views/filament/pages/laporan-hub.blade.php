<x-filament-panels::page>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($this->getLaporanList() as $item)
            <a href="{{ $item['url'] }}"
               class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 transition shadow-sm">
                <x-dynamic-component :component="$item['icon']" class="w-8 h-8 text-primary-600" />
                <span class="font-medium text-gray-900 dark:text-white">{{ $item['label'] }}</span>
            </a>
        @empty
            <p class="text-gray-500">Belum ada laporan yang bisa diakses.</p>
        @endforelse
    </div>
</x-filament-panels::page>