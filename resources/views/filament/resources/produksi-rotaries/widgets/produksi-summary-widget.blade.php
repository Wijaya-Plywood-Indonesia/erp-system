`<x-filament::widget>
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

    {{-- ================= REKAP PER KW ================= --}}
    <div class="space-y-3">
      <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
        Rekap per KW
      </div>

      {{-- WRAPPER --}}
      <div
        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700"
      >
        <div class="flex flex-wrap gap-2">
          @foreach ($summary['perKw'] ?? [] as $row)
          <div
            class="flex-1 min-w-[80px] rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-gray-900/50"
          >
            <div
              class="text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase"
            >
              KW {{ $row->kw }}
            </div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">
              {{ number_format($row->total) }}
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
    {{-- ================= REKAP PER JENIS KAYU ================= --}}
    <div class="space-y-3">
      <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
        Rekap per Jenis Kayu
      </div>

      @php $jenisGrouped = []; foreach ($summary['perJenisKayuKw'] ?? [] as
      $row) { $jenisGrouped[$row->jenis_kayu][] = $row; } @endphp

      <div class="grid grid-cols-1 gap-5">
        @foreach ($jenisGrouped as $jenisKayu => $items) @php $totalJenis =
        collect($items)->sum('total'); @endphp
        <div
          class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700"
        >
          <div class="flex justify-between items-center mb-4">
            <div class="font-semibold text-gray-800 dark:text-gray-200">
              {{ $jenisKayu }}
            </div>
            <div class="font-bold text-primary-600 dark:text-primary-400">
              {{ number_format($totalJenis) }}
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            @foreach ($items as $row)
            <div
              class="flex-1 min-w-[80px] rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-gray-900/50"
            >
              <div
                class="text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase"
              >
                KW {{ $row->kw }}
              </div>
              <div
                class="font-semibold text-lg text-gray-900 dark:text-gray-100"
              >
                {{ number_format($row->total) }}
              </div>
            </div>
            @endforeach
          </div>
        </div>
        @endforeach
      </div>
    </div>

    {{-- ================= REKAP PRODUKSI PER LAHAN ================= --}}
    <div class="space-y-4">
      <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
        Rekap Produksi per Lahan
      </div>

      @php $lahanGrouped = []; foreach ($summary['perLahanJenisKayuKw'] ?? [] as
      $row) { $lahanGrouped[$row->lahan_id][] = $row; } @endphp

      <div class="grid grid-cols-1 gap-6">
        @foreach ($lahanGrouped as $itemsLahan) @php $first = $itemsLahan[0];
        $totalLahan = collect($itemsLahan)->sum('total'); $jenisInLahan =
        collect($itemsLahan)->groupBy('jenis_kayu'); @endphp

        <div
          class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:bg-gray-800 dark:border-gray-700"
        >
          <div
            class="flex justify-between items-start mb-6 border-b border-gray-100 pb-4 dark:border-gray-700"
          >
            <div>
              <div class="font-bold text-lg text-gray-900 dark:text-white">
                {{ $first->kode_lahan }} - {{ $first->nama_lahan }}
              </div>
              <div
                class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider"
              >
                Sumber Lahan
              </div>
            </div>
            <div
              class="text-ls font-black text-primary-600 dark:text-primary-400"
            >
              {{ number_format($totalLahan) }}
            </div>
          </div>

          <div class="grid grid-cols-1 gap-6">
            @foreach ($jenisInLahan as $jenisKayu => $rows)
            <div class="space-y-2">
              <div
                class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-tighter"
              >
                {{ $jenisKayu }}
              </div>

              <div class="flex flex-wrap gap-2">
                @foreach ($rows as $row)
                <div
                  class="flex-1 min-w-[70px] rounded-lg bg-gray-50 px-3 py-1.5 text-center dark:bg-gray-900/50"
                >
                  <div class="text-[9px] text-gray-500 dark:text-gray-400">
                    KW {{ $row->kw }}
                  </div>
                  <div
                    class="font-bold text-lg text-gray-900 dark:text-gray-100"
                  >
                    {{ number_format($row->total) }}
                  </div>
                </div>
                @endforeach
              </div>
            </div>
            @endforeach
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </x-filament::card> </x-filament::widget
>`
