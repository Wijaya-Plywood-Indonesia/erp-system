<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if($isLoading)
    <x-filament::section>
        <div class="text-center py-8 text-gray-500">
            Memuat data...
        </div>
    </x-filament::section>
    @elseif(!empty($laporan))
    <x-filament::section heading="Data Produksi Guellotine">
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-blue-100">
                        <th class="border px-3 py-2 text-center">Tanggal</th>
                        <th class="border px-3 py-2 text-center">p</th>
                        <th class="border px-3 py-2 text-center">l</th>
                        <th class="border px-3 py-2 text-center">t</th>
                        <th class="border px-3 py-2 text-center">Jenis</th>
                        <th class="border px-3 py-2 text-center bg-yellow-200">byk</th>
                        <th class="border px-3 py-2 text-center bg-yellow-200">m3</th>
                        <th class="border px-3 py-2 text-center bg-yellow-200">TTL PKJ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($laporan as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="border px-3 py-1">{{ $row['tanggal'] }}</td>
                        <td class="border px-3 py-1 text-center">{{ $row['p'] }}</td>
                        <td class="border px-3 py-1 text-center">{{ $row['l'] }}</td>
                        <td class="border px-3 py-1 text-center">{{ $row['t'] }}</td>
                        <td class="border px-3 py-1 text-center">{{ $row['jenis'] }}</td>
                        <td class="border px-3 py-1 text-center">{{ $row['byk'] }}</td>
                        <td class="border px-3 py-1 text-center">{{ $row['m3'] }}</td>
                        <td class="border px-3 py-1 text-center">{{ $row['ttl_pkj'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
                {{-- Grand Total --}}
                <tfoot>
                    <tr class="bg-yellow-200 font-bold">
                        <td class="border px-3 py-2 text-center" colspan="5">TOTAL</td>
                        <td class="border px-3 py-2 text-center">
                            {{ collect($laporan)->sum('byk') }}
                        </td>
                        <td class="border px-3 py-2 text-center">
                            {{ round(collect($laporan)->sum('m3'), 3) }}
                        </td>
                        <td class="border px-3 py-2 text-center">
                            {{ collect($laporan)->max('ttl_pkj') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>
    @else
    <x-filament::section>
        <div class="text-center py-8 text-gray-500">
            Tidak ada data untuk tanggal yang dipilih.
        </div>
    </x-filament::section>
    @endif
</x-filament-panels::page>