{{-- resources/views/livewire/partials/search-select.blade.php --}}
<div class="relative" @click.outside="open = false" @keydown.escape.window="open = false">

    {{-- Trigger --}}
    <button
        type="button"
        x-ref="trigger"
        @click="toggle()"
        class="flex items-center justify-between gap-2 w-36 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700/80 hover:border-gray-400 dark:hover:border-gray-500 text-left px-2.5 py-1.5 text-xs transition-colors"
        :class="open ? 'border-orange-500 ring-1 ring-orange-500' : ''"
    >
        <span class="truncate"
              :class="selectedLabel ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500'"
              x-text="selectedLabel ?? 'Pilih...'"></span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400 shrink-0 transition-transform"
             :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    {{-- Panel dropdown: position fixed, background SOLID di kedua mode --}}
    <div
        x-show="open"
        x-cloak
        :style="dropdownStyle"
        @scroll.window="open && positionDropdown()"
        @resize.window="open && positionDropdown()"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-xl shadow-gray-300/50 dark:shadow-black/60 overflow-hidden"
    >
        {{-- Search --}}
        <div class="p-2 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800">
            <input
                type="text"
                x-model="search"
                x-ref="searchInput"
                placeholder="Cari..."
                @click.stop
                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-orange-500 focus:border-orange-500"
            />
        </div>

        {{-- Options --}}
        <ul class="max-h-52 overflow-y-auto bg-white dark:bg-gray-800 py-1">
            <template x-for="o in filtered" :key="o.id">
                <li
                    @click="select(o.id)"
                    x-text="o.label"
                    class="px-3 py-1.5 text-xs cursor-pointer transition-colors"
                    :class="String(o.id) === String(selected)
                        ? 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300'
                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700'"
                ></li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-xs text-gray-400 dark:text-gray-500 text-center">
                Tidak ditemukan
            </li>
        </ul>
    </div>
</div>