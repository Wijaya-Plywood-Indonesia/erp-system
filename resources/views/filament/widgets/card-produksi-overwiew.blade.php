<x-filament-widgets::widget>
    {{-- <div class="flex flex-wrap gap-4 w-full bg-gray-100"> --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
        
        <x-overview-produksi-card
                title="Produksi Jati"
                :totalProduksi="10000"
                satuanProduksi="m³"
                :totalPegawai="30"
                :detailUkuran="[
                        ['ukuran' => '200cm (Grade A)', 'jumlah' => 5000],
                        ['ukuran' => '400cm (Grade B)', 'jumlah' => 3000],
                        ['ukuran' => '600cm (Grade C)', 'jumlah' => 2000]
                    ]" 
        />

        <x-overview-produksi-card
                title="Produksi Mahoni"
                :totalProduksi="8500"
                satuanProduksi="m³"
                :totalPegawai="20"
                :detailUkuran="[
                        ['ukuran' => '200cm (Grade A)', 'jumlah' => 4000],
                        ['ukuran' => '400cm (Grade B)', 'jumlah' => 4500]
                    ]" 
        />

        <x-overview-produksi-card
                title="Produksi Sengon"
                :totalProduksi="5000"
                satuanProduksi="m³"
                :totalPegawai="15"
                :detailUkuran="[
                        ['ukuran' => '200cm (Grade A)', 'jumlah' => 5000]
                    ]" 
        />

    </div>
</x-filament-widgets::widget>