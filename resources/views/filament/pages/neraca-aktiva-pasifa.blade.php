<x-filament-panels::page>
    <div class="space-y-6">

        <!-- Header -->
        <div class="text-center space-y-1">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                NERACA PERUSAHAAN WIJAYA
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Per {{ now()->format('d F Y') }}
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- ===================== AKTIVA ===================== -->
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
                <div class="mb-3">
                    <span class="px-3 py-1 text-sm font-semibold bg-yellow-400 text-black rounded">
                        AKTIVA
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                                <th class="py-2">No Akun</th>
                                <th>Keterangan</th>
                                <th class="text-right">Nilai</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">

                            @forelse($this->aktiva as $akun)
                                <tr>
                                    <td class="py-2">
                                        {{ $akun->kode_anak_akun ?? $akun['kode'] }}
                                    </td>

                                    <td>
                                        {{ $akun->nama_anak_akun ?? $akun['nama'] }}
                                    </td>

                                    <td class="text-right">
                                        {{ number_format($akun->total ?? $akun['total'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-gray-500">
                                        Tidak ada data aktiva
                                    </td>
                                </tr>
                            @endforelse

                            <!-- TOTAL -->
                            <tr class="font-semibold bg-yellow-50 dark:bg-yellow-900/30">
                                <td colspan="2" class="text-right py-2">
                                    TOTAL AKTIVA
                                </td>
                                <td class="text-right">
                                    {{ number_format($this->totalAktiva, 0, ',', '.') }}
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>
            </div>


            <!-- ===================== PASIVA ===================== -->
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
                <div class="mb-3">
                    <span class="px-3 py-1 text-sm font-semibold bg-yellow-400 text-black rounded">
                        PASIVA
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                                <th class="py-2">No Akun</th>
                                <th>Keterangan</th>
                                <th class="text-right">Nilai</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">

                            @forelse($this->pasiva as $akun)
                                <tr>
                                    <td class="py-2">
                                        {{ $akun->kode_anak_akun ?? $akun['kode'] }}
                                    </td>

                                    <td>
                                        {{ $akun->nama_anak_akun ?? $akun['nama'] }}
                                    </td>

                                    <td class="text-right">
                                        {{ number_format($akun->total ?? $akun['total'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-gray-500">
                                        Tidak ada data pasiva
                                    </td>
                                </tr>
                            @endforelse

                            <!-- TOTAL -->
                            <tr class="font-semibold bg-yellow-50 dark:bg-yellow-900/30">
                                <td colspan="2" class="text-right py-2">
                                    TOTAL PASIVA
                                </td>
                                <td class="text-right">
                                    {{ number_format($this->totalPasiva, 0, ',', '.') }}
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- ===================== BALANCE CHECK ===================== -->
        <div class="text-center">
            @if($this->totalAktiva == $this->totalPasiva)
                <div class="text-green-600 font-semibold">
                    Neraca Balance ✅
                </div>
            @else
                <div class="text-red-600 font-semibold">
                    Neraca Tidak Balance ❌
                </div>
            @endif
        </div>

    </div>
</x-filament-panels::page>
