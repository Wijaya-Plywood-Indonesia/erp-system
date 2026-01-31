<x-filament-widgets::widget>
    {{-- <div class="flex flex-wrap gap-4 w-full bg-gray-100"> --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
        
        @foreach ($cards as $card)
            <x-overview-produksi-card
                :title="$card['name']"
                :totalProduksi="$card['total_produksi']"
                :satuanProduksi="$card['satuan_hasil']"
                :totalPegawai="$card['total_pegawai']"
                :detailUkuran="$card['rekap_ukuran']"
                {{-- :url="route('filament.admin.resources.' . $card['urlResource'] . '.index')" --}}
            />
        @endforeach

    </div>
</x-filament-widgets::widget>