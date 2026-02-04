<x-filament::widget>
  <x-filament::card
    class="w-full space-y-10 dark:bg-gray-900 dark:border-gray-800"
  >
    {{-- ================= TOTAL PRODUKSI ================= --}}
    <div class="text-center py-4">
      <div
        class="text-4xl font-extrabold text-primary-600 dark:text-primary-500"
      >
        {{ number_format($summary["totalAll"] ?? 0) }}
      </div>
      <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
        Total Produksi (Lembar)
      </div>
    </div>

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

    @forelse ($summary['globalTarget'] ?? [] as $item)
    <div class="mt-6 space-y-4">
    <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
        Progress Target Kayu {{$item['nama_kayu']}}  
        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
            ( Target {{ $item['target'] == 0 ? "Belum di Set" : $item['target'] }} )
        </span>
    </div>

        @php
            // pastikan numeric & dibatasi max 100
            $progress = min(100, max(0, (float) $item['progress'] ));
            // $progress = min(100, max(0, (float)$item['target'] === 0 && $item['progress'] !== 0 ? 100 :(float) $item['progress'] ));
        @endphp

        <div
            class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm
                   dark:bg-gray-800 dark:border-gray-700 space-y-2">

            {{-- Nama & Nilai --}}
            <div class="flex justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">
                    Ukuran {{$item['ukuran_formatted']}}
                </span>
                <span class="text-gray-600 dark:text-gray-400">
                  {{ number_format($item['total_produksi']) }}
                  / {{ (float)$item['target'] }}
                </span>
            </div>
        {{-- Progress Bar --}}
        <div class="w-full h-3 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
            <div
                class="h-full rounded-full transition-all duration-500"
                style="
                    width: {{ $progress }}%;
                    background-color:
                        {{ $progress >= 100
                            ? '#16a34a'   /* green-600 */
                            : ($progress >= 75
                                ? '#2563eb' /* blue-600 */
                                : '#f59e0b' /* amber-500 */) }};
                ">
            </div>
        </div>
                    {{-- Persentase --}}
                    <div class="text-xs text-right text-gray-500 dark:text-gray-400">
                        {{ number_format($progress, 1) }}%
                    </div>
                </div>
      </div>
              @empty
                  <div class="text-sm  text-center text-gray-500 dark:text-gray-400 italic">
                      Belum ada data progress kayu.
                  </div>
              @endforelse

  </x-filament::card>
</x-filament::widget>