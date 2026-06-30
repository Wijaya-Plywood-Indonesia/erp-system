<x-filament-widgets::widget>
    {{-- <div class="flex flex-wrap gap-4 w-full bg-gray-100"> --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
        
        @foreach ($cards as $card)
            <x-overview-produksi-card
                :name="$card['name']"
                :urlResource="$card['urlResource']" 
                :totalProduksi="$card['total_produksi']" 
                :totalPegawai="$card['total_pegawai']" 
                :satuanHasil="$card['satuan_hasil']" 
                :dataRekap="$card['data_rekap']"

                {{-- :url="route('filament.admin.resources.' . $card['urlResource'] . '.index')" --}}
            />
        @endforeach

    </div>
</x-filament-widgets::widget>