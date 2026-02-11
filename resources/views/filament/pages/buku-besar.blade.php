<x-filament-panels::page>
    <div class="space-y-6">

        @foreach($indukAkuns as $induk)

        <div 
            x-data="{ open: false }"
            class="bg-gray-900 rounded-xl border border-gray-700 overflow-hidden"
        >

            {{-- HEADER INDUK --}}
            <div 
                @click="open = !open"
                class="flex justify-between items-center px-6 py-4 cursor-pointer select-none hover:bg-gray-800 transition"
            >
                <div class="text-lg font-bold text-white">
                    no akun: {{ $induk->kode_induk_akun }} - {{ $induk->nama_induk_akun }}
                </div>

                <div class="font-semibold text-white">
                    Sisa Saldo:
                    Rp {{ number_format($this->getSaldoInduk($induk->kode_induk_akun)) }}
                </div>
            </div>

            {{-- BODY --}}
            <div 
                x-show="open"
                x-transition
                class="p-4 space-y-3 bg-gray-900 border-t border-gray-700"
            >
                @foreach($induk->anakAkuns as $anak)
                    @include('filament.pages.partials.buku-besar-anak', ['akun' => $anak])
                @endforeach
            </div>

        </div>

        @endforeach

    </div>
</x-filament-panels::page>
