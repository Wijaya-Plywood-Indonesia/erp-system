<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

        @php
        // Definisikan daftar menu stok Anda di sini
        $stokMenus = [
        ['title' => 'Stok Kayu', 'url' => url('/admin/stok-kayu'), 'icon' => 'heroicon-m-document-text'],
        ['title' => 'Stok Veneer Basah', 'url' => url('/admin/stok-veneer-basah'), 'icon' => 'heroicon-m-document-text'],
        ['title' => 'Stok Veneer Kering', 'url' => url('/admin/stok-veneer-kering'), 'icon' => 'heroicon-m-document-text'],
        ['title' => 'Stok Veneer Jadi', 'url' => url('/admin/stok-veneer-jadi'), 'icon' => 'heroicon-m-document-text'],
        ['title' => 'Stok Platform MTH', 'url' => url('/admin/stok-platform-mth'), 'icon' => 'heroicon-m-document-text'],
        ['title' => 'Stok Platform Jadi', 'url' => url('/admin/stok-platform-jadi'), 'icon' => 'heroicon-m-document-text'],
        ['title' => 'Stok Triplek MTH', 'url' => url('/admin/stok-triplek-mth'), 'icon' => 'heroicon-m-document-text'],
        ['title' => 'Stok Triplek Jadi', 'url' => url('/admin/stok-triplek-jadi'), 'icon' => 'heroicon-m-document-text'],
        ['title' => 'Stok Plywood Siap Jual', 'url' => url('/admin/stok-plywood-siap-jual'), 'icon' => 'heroicon-m-document-text'],
        ];
        @endphp

        @foreach($stokMenus as $menu)
        <a href="{{ $menu['url'] }}"
            class="flex items-center gap-3 p-4 bg-zinc-900 border border-zinc-800 rounded-xl transition duration-200 hover:border-amber-500 hover:bg-zinc-800/50 group">

            <!-- Icon Box/Document dengan warna orange sesuai tema gambar Anda -->
            <div class="p-2 text-amber-500 bg-amber-500/10 rounded-lg group-hover:bg-amber-500 group-hover:text-zinc-950 transition">
                <x-filament::icon
                    alias="panels::pages.dashboard.navigation-item"
                    icon="{{ $menu['icon'] }}"
                    class="h-5 w-5" />
            </div>

            <!-- Judul Menu -->
            <span class="text-sm font-medium text-zinc-200 group-hover:text-white">
                {{ $menu['title'] }}
            </span>
        </a>
        @endforeach

    </div>
</x-filament-panels::page>