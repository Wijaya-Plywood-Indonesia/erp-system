<x-filament::page>

    {{-- HEADER --}}
    <div class="grid grid-cols-3 gap-4">
    <input type="date" wire:model="tanggal"
        class="border rounded p-2
               bg-white dark:bg-gray-900
               text-gray-800 dark:text-gray-100
               border-gray-300 dark:border-gray-700">

    <input wire:model="kode_jurnal" placeholder="Kode Jurnal"
        class="border rounded p-2
               bg-white dark:bg-gray-900
               text-gray-800 dark:text-gray-100
               border-gray-300 dark:border-gray-700">

    <input wire:model="no_dokumen" placeholder="No Dokumen"
        class="border rounded p-2
               bg-white dark:bg-gray-900
               text-gray-800 dark:text-gray-100
               border-gray-300 dark:border-gray-700">
</div>


    {{-- FORM --}}
    <div class="mt-6 border rounded-lg p-4 grid grid-cols-2 gap-4
            bg-white dark:bg-gray-900
            border-gray-200 dark:border-gray-700
            text-gray-800 dark:text-gray-100">


        {{-- NO AKUN --}}
        <div>
            <label class="text-sm font-medium">No Akun</label>
            <select wire:model.live="form.no_akun"
    class="border rounded p-2 w-full
           bg-white dark:bg-gray-800
           text-gray-800 dark:text-gray-100
           border-gray-300 dark:border-gray-600">

                <option value="">-- Pilih Akun --</option>
                @foreach ($akunList as $a)
                <option value="{{ $a->kode_sub_anak_akun }}">
                    {{ $a->kode_sub_anak_akun }} - {{ $a->nama_sub_anak_akun }}
                </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-sm font-medium">Nama Akun</label>
            <input wire:model="form.nama_akun" readonly
    class="border rounded p-2 w-full
           bg-gray-100 dark:bg-gray-800
           text-gray-700 dark:text-gray-300
           border-gray-300 dark:border-gray-600">

        </div>

        {{-- NAMA --}}
        <div>
            <label class="text-sm font-medium">Nama</label>
            <input wire:model="form.nama"
    class="border rounded p-2 w-full
           bg-white dark:bg-gray-800
           text-gray-800 dark:text-gray-100
           border-gray-300 dark:border-gray-600">

        </div>

        {{-- MM --}}
        <div>
            <label class="text-sm font-medium">MM (Tebal Plywood)</label>
            <input wire:model="form.mm" class="border rounded p-2 w-full
           bg-white dark:bg-gray-800
           text-gray-800 dark:text-gray-100
           border-gray-300 dark:border-gray-600">
        </div>

        {{-- KETERANGAN --}}
        <div class="col-span-2">
            <label class="text-sm font-medium">Keterangan</label>
            <textarea wire:model="form.keterangan"
    class="border rounded p-2 w-full
           bg-white dark:bg-gray-800
           text-gray-800 dark:text-gray-100
           border-gray-300 dark:border-gray-600"></textarea>

        </div>

        {{-- POSISI --}}
        <div class="col-span-2">
            <label class="text-sm font-medium">Posisi</label>
            <div class="flex gap-6 mt-1">
                <label class="flex items-center gap-2">
                    <input type="radio" wire:model="form.map" value="D"> Debit
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" wire:model="form.map" value="K"> Kredit
                </label>
            </div>
        </div>

        {{-- HIT KBK --}}
        <div class="col-span-2">
            <label class="text-sm font-medium">Hit KBK</label>
            <select wire:model="form.hit_kbk" class="border rounded p-2 w-full bg-white dark:bg-gray-800
           text-gray-800 dark:text-gray-100
           border-gray-300 dark:border-gray-600">
                <option value="">-- Pilih --</option>
                <option value="banyak">Banyak</option>
                <option value="m3">Kubikasi (M3)</option>
            </select>
        </div>

        {{-- BANYAK --}}
        <div>
            <label class="text-sm font-medium">Banyak</label>
            <input type="number" wire:model="form.banyak" class="border rounded p-2 w-full
           bg-white dark:bg-gray-800
           text-gray-800 dark:text-gray-100
           border-gray-300 dark:border-gray-600">
        </div>

        {{-- M3 --}}
        <div>
            <label class="text-sm font-medium">Kubikasi (M3)</label>
            <input type="number" step="0.0001" wire:model="form.m3" class="border rounded p-2 w-full
           bg-white dark:bg-gray-800
           text-gray-800 dark:text-gray-100
           border-gray-300 dark:border-gray-600">
        </div>

        {{-- HARGA --}}
        <div class="col-span-2">
            <label class="text-sm font-medium">Harga</label>
            <input type="number" wire:model="form.harga" class="border rounded p-2 w-full bg-white dark:bg-gray-800
           text-gray-800 dark:text-gray-100
           border-gray-300 dark:border-gray-600">
        </div>

        <button wire:click="addItem" class="col-span-2 bg-primary-600 hover:bg-primary-700 text-white rounded p-2">
            + Tambah ke Draft
        </button>
    </div>

    {{-- DRAFT JURNAL --}}
    <h3 class="mt-8 font-bold">Draft Jurnal</h3>

    <div class="overflow-x-auto border rounded-lg mt-2
            border-gray-200 dark:border-gray-700">
        <table class="min-w-[1200px] w-full text-sm border-collapse">
            <thead class="bg-gray-100 dark:bg-gray-800 sticky top-0">
                <tr class="border-b border-gray-200 dark:border-gray-700
           hover:bg-gray-50 dark:hover:bg-gray-800">
                    <th class="px-2 py-1 w-[120px]">No Akun</th>
                    <th class="px-2 py-1 w-[220px]">Nama Akun</th>
                    <th class="px-2 py-1 w-[150px]">Nama</th>
                    <th class="px-2 py-1 w-[60px]">D/K</th>
                    <th class="px-2 py-1 w-[80px]">KBK</th>
                    <th class="px-2 py-1 w-[90px] text-right">Banyak</th>
                    <th class="px-2 py-1 w-[90px] text-right">M3</th>
                    <th class="px-2 py-1 w-[120px] text-right">Harga</th>
                    <th class="px-2 py-1 w-[130px] text-right">Total</th>
                    <th class="px-2 py-1 w-[40px]"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $i => $row)
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-2 py-1">{{ $row['no_akun'] }}</td>
                    <td class="px-2 py-1">{{ $row['nama_akun'] }}</td>
                    <td class="px-2 py-1">{{ $row['nama'] }}</td>
                    <td class="px-2 py-1 text-center">{{ $row['map'] }}</td>
                    <td class="px-2 py-1">{{ $row['hit_kbk'] }}</td>
                    <td class="px-2 py-1 text-right">{{ $row['banyak'] }}</td>
                    <td class="px-2 py-1 text-right">{{ $row['m3'] }}</td>
                    <td class="px-2 py-1 text-right">{{ number_format($row['harga']) }}</td>
                    <td class="px-2 py-1 text-right font-semibold">
                        {{ number_format($row['total']) }}
                    </td>
                    <td class="px-2 py-1 text-center">
                        <button wire:click="removeItem({{ $i }})"
    class="text-red-600 dark:text-red-400 font-bold">
    ✕
</button>

                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- TOTAL --}}
    <div class="mt-4 flex gap-10 font-bold">
        <div>Total Debit: {{ number_format($this->totalDebit) }}</div>
        <div>Total Kredit: {{ number_format($this->totalKredit) }}</div>
    </div>

    <button wire:click="saveJurnal" class="mt-4 bg-green-600 text-white rounded px-4 py-2" @disabled($this->totalDebit
        !== $this->totalKredit)>
        Simpan Jurnal
    </button>

    <hr class="my-10">

    {{-- JURNAL FINAL --}}
    <h3 class="font-bold text-lg mb-3">📘 Jurnal Umum (Final)</h3>

    @if ($jurnals->where('status', 'belum sinkron')->count())
    <button wire:click="confirmSync" class="mb-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
        🔄 Sinkronisasi Jurnal
    </button>

    @endif


    <div class="overflow-x-auto border rounded-lg
            border-gray-200 dark:border-gray-700">
        <table class="min-w-[1800px] w-full text-sm border-collapse">
            <thead class="bg-gray-100 dark:bg-gray-800 sticky top-0">
                <tr class="border-b border-gray-200 dark:border-gray-700
           hover:bg-gray-50 dark:hover:bg-gray-800">
                    <th class="px-2 py-1 w-[220px]">Nama Akun</th>
                    <th class="px-2 py-1 w-[100px]">Tgl</th>
                    <th class="px-2 py-1 w-[80px]">Jurnal</th>
                    <th class="px-2 py-1 w-[120px]">No Akun</th>
                    <th class="px-2 py-1 w-[120px]">No Dok</th>
                    <th class="px-2 py-1 w-[150px]">Nama</th>
                    <th class="px-2 py-1 w-[350px]">Keterangan</th>
                    <<th class="px-2 py-1 w-[80px] text-center">Map</th>
                        <th class="px-2 py-1 w-[60px]">MM</th>
                        <th class="px-2 py-1 w-[80px]">hit kbk</th>
                        <th class="px-2 py-1 w-[90px] text-right">Banyak</th>
                        <th class="px-2 py-1 w-[90px] text-right">M3</th>
                        <th class="px-2 py-1 w-[120px] text-right">Harga</th>
                        <th class="px-2 py-1 w-[130px] text-right">Total</th>
                        <th class="px-2 py-1 w-[120px]">Dibuat oleh</th>
                        <th class="px-2 py-1 w-[90px]">Status</th>
                        <th class="px-2 py-1 w-[160px]">Disinkron pada</th>
                        <th class="px-2 py-1 w-[140px]">Disinkron oleh</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jurnals as $j)
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-2 py-1">{{ $j->nama_akun }}</td>
                    <td class="px-2 py-1">{{ $j->tgl?->format('Y-m-d') }}</td>
                    <td class="px-2 py-1">{{ $j->jurnal }}</td>
                    <td class="px-2 py-1">{{ $j->no_akun }}</td>
                    <td class="px-2 py-1">{{ $j->no_dokumen }}</td>
                    <td class="px-2 py-1">{{ $j->nama }}</td>
                    <td class="px-2 py-1 whitespace-normal">{{ $j->keterangan }}</td>
                    <td class="px-2 py-1 text-center font-semibold">
                        {{ $j->map === 'D' ? 'Debit' : 'Kredit' }}
                    </td>
                    <td class="px-2 py-1">{{ $j->mm }}</td>
                    <td class="px-2 py-1">{{ $j->hit_kbk }}</td>
                    <td class="px-2 py-1 text-right">{{ $j->banyak }}</td>
                    <td class="px-2 py-1 text-right">{{ $j->m3 }}</td>
                    <td class="px-2 py-1 text-right">{{ number_format($j->harga) }}</td>
                    <td class="px-2 py-1 text-right font-semibold">
                        {{ number_format(
                        ($j->hit_kbk === 'banyak'
                        ? ($j->banyak ?? 0)
                        : ($j->m3 ?? 0)
                        ) * ($j->harga ?? 0)
                        ) }}
                    </td>
                    <td class="px-2 py-1">{{ $j->created_by }}</td>
                    <td class="px-2 py-1 font-semibold text-green-600">{{ $j->status }}</td>
                    <td class="px-2 py-1">
                        {{ $j->synced_at?->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-2 py-1">
                        {{ $j->synced_by }}
                    </td>

                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</x-filament::page>