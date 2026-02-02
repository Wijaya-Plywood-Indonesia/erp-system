<x-filament::widget>
  <x-filament::card class="w-full space-y-8">

    {{-- Header Stat Utama --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 py-6 border-b dark:border-gray-700">
      <div class="text-center border-r dark:border-gray-700 last:border-0">
        <div class="text-5xl font-black text-primary-600 tracking-tight">
          {{ number_format($summary['totalAll'] ?? 0) }}
        </div>
        <p class="text-sm font-semibold uppercase tracking-wider text-gray-500 mt-1">Total Lembar Produksi</p>
      </div>

      <div class="text-center">
        <div class="text-5xl font-black text-success-600 tracking-tight">
          {{ number_format($summary['totalPegawai'] ?? 0) }}
        </div>
        <p class="text-sm font-semibold uppercase tracking-wider text-gray-500 mt-1">Personil Terlibat</p>
      </div>
    </div>

    {{-- Section 1: Ukuran + KW + Jenis Kayu --}}
    <div class="space-y-4">
      <h3 class="text-lg font-bold flex items-center gap-2 text-gray-800 dark:text-gray-200">
        Rincian Ukuran, KW & Jenis Kayu
      </h3>

      <div class="grid grid-cols-1">
        @foreach ($summary['globalUkuranKwJenis'] as $row)
        <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div class="space-y-1">
            <div class="text-base font-bold text-gray-900 dark:text-white">
              {{ $row->ukuran }} + KW {{ $row->kw }} + {{ $row->jenis_kayu }}
            </div>
          </div>
          <div class="text-2xl font-black text-gray-900 dark:text-white">
            {{ number_format($row->total) }}
          </div>
        </div>
        @endforeach
      </div>
    </div>

    {{-- Section 2: Global Ukuran --}}
    <div class="space-y-4">
      <h3 class="text-lg font-bold flex items-center gap-2 text-gray-800 dark:text-gray-200">
        Akumulasi Per Ukuran
      </h3>

      <div class="grid grid-cols-1">
        @foreach ($summary['globalUkuran'] as $row)
        <div class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 flex justify-between items-center">
          <div class="text-base font-medium text-gray-500 dark:text-gray-400 truncate">{{ $row->ukuran }}</div>
          <div class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($row->total) }}</div>
        </div>
        @endforeach
      </div>
    </div>

  </x-filament::card>
</x-filament::widget>