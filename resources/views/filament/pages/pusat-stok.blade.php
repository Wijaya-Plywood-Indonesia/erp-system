<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

        @php
        $stokMenus = [
        ['title' => 'Stok Kayu', 'url' => url('/admin/stok-kayu'), 'icon' => 'heroicon-o-cube'],
        ['title' => 'Stok Veneer Basah', 'url' => url('/admin/stok-veneer-basah'), 'icon' => 'heroicon-o-beaker'],
        ['title' => 'Stok Veneer Kering', 'url' => url('/admin/stok-veneer-kering'), 'icon' => 'heroicon-o-sun'],
        ['title' => 'Stok Veneer Jadi', 'url' => url('/admin/stok-veneer-jadi'), 'icon' => 'heroicon-o-squares-2x2'],
        ['title' => 'Stok Platform MTH', 'url' => url('/admin/stok-platform-mth'), 'icon' => 'heroicon-o-rectangle-stack'],
        ['title' => 'Stok Platform Jadi', 'url' => url('/admin/stok-platform-jadi'), 'icon' => 'heroicon-o-square-3-stack-3d'],
        ['title' => 'Stok Triplek MTH', 'url' => url('/admin/stok-triplek-mth'), 'icon' => 'heroicon-o-rectangle-group'],
        ['title' => 'Stok Triplek Jadi', 'url' => url('/admin/stok-triplek-jadi'), 'icon' => 'heroicon-o-squares-plus'],
        ['title' => 'Stok Gudang Satu', 'url' => \App\Filament\Pages\StokGudangSatu::getUrl(), 'icon' => 'heroicon-o-building-storefront'],
        ['title' => 'Stok Plywood Siap Jual', 'url' => url('/admin/stok-plywood-siap-jual'), 'icon' => 'heroicon-o-truck'],
        ];
        @endphp

        @foreach($stokMenus as $menu)
        <a href="{{ $menu['url'] }}"
            class="flex items-center gap-3 p-4 transition duration-200 rounded-xl group
                   bg-white border border-gray-200 hover:border-amber-500 hover:bg-gray-50
                   dark:bg-zinc-900 dark:border-zinc-800 dark:hover:border-amber-500 dark:hover:bg-zinc-800/50">

            <div class="p-2 rounded-lg transition
                        text-amber-600 bg-amber-50 group-hover:bg-amber-500 group-hover:text-white
                        dark:text-amber-500 dark:bg-amber-500/10 dark:group-hover:bg-amber-500 dark:group-hover:text-zinc-950">
                <x-filament::icon
                    alias="panels::pages.dashboard.navigation-item"
                    icon="{{ $menu['icon'] }}"
                    class="h-5 w-5" />
            </div>

            <span class="text-sm font-medium transition
                         text-gray-700 group-hover:text-gray-900
                         dark:text-zinc-200 dark:group-hover:text-white">
                {{ $menu['title'] }}
            </span>
        </a>
        @endforeach

    </div>

    <hr class="border-gray-200 dark:border-white/10 my-6" />

    {{-- ================= SECTION 2: PUSAT LOG ================= --}}
    <div>
        <h2 class="text-2xl lg:text-3xl font-bold tracking-tight text-gray-950 dark:text-white mb-4">
            Pusat Log
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @php
            $logMenus = [
            ['title' => 'Log Harga Kayu', 'url' => url('/admin/log-harga-kayu'), 'icon' => 'heroicon-o-currency-dollar'],
            ['title' => 'Log HPP Kayu', 'url' => url('/admin/hpp-average-page'), 'icon' => 'heroicon-o-calculator'],
            ['title' => 'Log HPP Veneer Basah', 'url' => url('/admin/hpp-veneer-basah-page'), 'icon' => 'heroicon-o-calculator'],
            ['title' => 'Log HPP Veneer Kering', 'url' => url('/admin/hpp-veneer-kering-page'), 'icon' => 'heroicon-o-calculator'],
            ['title' => 'Log HPP Veneer Jadi', 'url' => url('/admin/hpp-veneer-jadi-page'), 'icon' => 'heroicon-o-calculator'],
            ['title' => 'Log HPP Platform MTH', 'url' => url('/admin/hpp-platform-mth-page'), 'icon' => 'heroicon-o-calculator'],
            ['title' => 'Log HPP Triplek MTH', 'url' => url('/admin/hpp-triplek-mth-page'), 'icon' => 'heroicon-o-calculator'],
            ['title' => 'Log HPP Platform Jadi', 'url' => url('/admin/hpp-platform-jadi-page'), 'icon' => 'heroicon-o-calculator'],
            ['title' => 'Log HPP Triplek Jadi', 'url' => url('/admin/hpp-triplek-jadi-page'), 'icon' => 'heroicon-o-calculator'],
            ['title' => 'Log Gudang Satu', 'url' => url('/admin/gudang-satu-log-page'), 'icon' => 'heroicon-o-archive-box'],
            ['title' => 'Log Plywood Siap Jual', 'url' => url('/admin/hpp-plywood-siap-jual-page'), 'icon' => 'heroicon-o-clipboard-document-check'],
            ];
            @endphp

            @foreach($logMenus as $log)
            <a href="{{ $log['url'] }}" class="flex items-center gap-3 p-4 transition duration-200 rounded-xl group bg-white border border-gray-200 hover:border-amber-500 hover:bg-gray-50 dark:bg-white/5 dark:border-white/10 dark:hover:border-amber-500 dark:hover:bg-white/10">
                <div class="p-2 transition rounded-lg text-amber-600 bg-amber-50 dark:text-amber-400 dark:bg-amber-500/10 group-hover:bg-amber-500 group-hover:text-white dark:group-hover:text-gray-950">
                    <x-filament::icon icon="{{ $log['icon'] }}" class="h-5 w-5" />
                </div>
                <span class="text-sm font-medium transition text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white">
                    {{ $log['title'] }}
                </span>
            </a>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
