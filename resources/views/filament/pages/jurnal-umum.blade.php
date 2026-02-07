<x-filament::page>

{{-- HEADER --}}
<div class="grid grid-cols-3 gap-4">
    <input type="date" wire:model="tanggal" class="border rounded p-2">
    <input wire:model="kode_jurnal" placeholder="Kode Jurnal" class="border rounded p-2">
    <input wire:model="no_dokumen" placeholder="No Dokumen" class="border rounded p-2">
</div>

{{-- FORM --}}
<div class="mt-6 border rounded p-4 grid grid-cols-2 gap-4">

    {{-- NO AKUN --}}
    <div class="col-span-2">
        <label class="text-sm font-medium">No Akun</label>
        <select wire:model="form.no_akun" class="border rounded p-2 w-full">
            <option value="">-- Pilih Akun --</option>
            @foreach ($akunList as $a)
                <option value="{{ $a->kode_sub_anak_akun }}">
                    {{ $a->kode_sub_anak_akun }} - {{ $a->nama_sub_anak_akun }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- NAMA --}}
    <div>
        <label class="text-sm font-medium">Nama</label>
        <input wire:model="form.nama" class="border rounded p-2 w-full">
    </div>

    {{-- MM --}}
    <div>
        <label class="text-sm font-medium">MM (Tebal Plywood)</label>
        <input wire:model="form.mm" class="border rounded p-2 w-full">
    </div>

    {{-- KETERANGAN --}}
    <div class="col-span-2">
        <label class="text-sm font-medium">Keterangan</label>
        <textarea wire:model="form.keterangan"
            class="border rounded p-2 w-full"></textarea>
    </div>

    {{-- DEBIT / KREDIT --}}
    <div class="col-span-2">
        <label class="text-sm font-medium">Posisi</label>
        <div class="flex gap-6 mt-1">
            <label class="flex items-center gap-2">
                <input type="radio" wire:model="form.map" value="D">
                Debit
            </label>
            <label class="flex items-center gap-2">
                <input type="radio" wire:model="form.map" value="K">
                Kredit
            </label>
        </div>
    </div>

    {{-- HITUNG BERDASARKAN --}}
    <div class="col-span-2">
        <label class="text-sm font-medium">Hitung Berdasarkan</label>
        <select wire:model="form.hit_kbk" class="border rounded p-2 w-full">
            <option value="">-- Pilih --</option>
            <option value="banyak">Banyak</option>
            <option value="m3">Kubikasi (M3)</option>
        </select>
    </div>

    {{-- BANYAK --}}
    <div>
        <label class="text-sm font-medium">Banyak</label>
        <input type="number" wire:model="form.banyak" class="border rounded p-2 w-full">
    </div>

    {{-- M3 --}}
    <div>
        <label class="text-sm font-medium">Kubikasi (M3)</label>
        <input type="number" step="0.0001"
            wire:model="form.m3"
            class="border rounded p-2 w-full">
    </div>

    {{-- HARGA --}}
    <div class="col-span-2">
        <label class="text-sm font-medium">Harga</label>
        <input type="number" wire:model="form.harga"
            class="border rounded p-2 w-full">
    </div>

    {{-- TAMBAH --}}
    <button wire:click="addItem"
        class="col-span-2 bg-primary-600 hover:bg-primary-700 text-white rounded p-2">
        + Tambah ke Draft
    </button>
</div>



{{-- TABEL SEMENTARA --}}
<h3 class="mt-8 font-bold">Draft Jurnal</h3>

<table class="w-full mt-2 text-sm border">
    <thead>
        <tr class="border-b">
            <th>Akun</th>
            <th>Nama</th>
            <th>DK</th>
            <th>Total</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $i => $row)
        <tr class="border-b">
            <td>{{ $row['no_akun'] }}</td>
            <td>{{ $row['nama'] }}</td>
            <td>{{ $row['map'] }}</td>
            <td>{{ number_format($row['total']) }}</td>
            <td>
                <button wire:click="removeItem({{ $i }})" class="text-red-500">
                    Hapus
                </button>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- TOTAL --}}
<div class="mt-4 flex gap-6 font-bold">
    <div>Total Debit: {{ number_format($this->totalDebit) }}</div>
    <div>Total Kredit: {{ number_format($this->totalKredit) }}</div>
</div>

{{-- SIMPAN --}}
<button wire:click="saveJurnal"
    class="mt-4 bg-green-600 text-white rounded p-2"
    @disabled($this->totalDebit !== $this->totalKredit)>
    Simpan Jurnal
</button>

<hr class="my-10">

<h3 class="font-bold text-lg mb-3">
    📘 Jurnal Umum (Final)
</h3>

<div class="overflow-x-auto border rounded">
<table class="w-full text-sm">
    <thead class="bg-gray-100 border-b">
        <tr>
            <th>Tgl</th>
            <th>Jurnal</th>
            <th>No Dok</th>
            <th>Akun</th>
            <th>Nama</th>
            <th>D</th>
            <th>K</th>
            <th>MM</th>
            <th>KBK</th>
            <th>Banyak</th>
            <th>M3</th>
            <th>Harga</th>
            <th>User</th>
            <th>Status</th>
        </tr>
    </thead>

    <tbody>
        @forelse ($jurnals as $j)
        <tr class="border-b hover:bg-gray-50">
            <td>{{ $j->tgl }}</td>
            <td>{{ $j->jurnal }}</td>
            <td>{{ $j->no_dokumen }}</td>
            <td>{{ $j->no_akun }}</td>
            <td>{{ $j->nama }}</td>

            <td class="text-right">
                {{ $j->map === 'D' ? number_format($j->harga) : '-' }}
            </td>
            <td class="text-right">
                {{ $j->map === 'K' ? number_format($j->harga) : '-' }}
            </td>

            <td>{{ $j->mm }}</td>
            <td>{{ $j->hit_kbk }}</td>
            <td>{{ $j->banyak }}</td>
            <td>{{ $j->m3 }}</td>
            <td class="text-right">{{ number_format($j->harga) }}</td>
            <td>{{ $j->created_by }}</td>
            <td>{{ $j->status }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="14" class="text-center py-4 text-gray-500">
                Belum ada jurnal tersimpan
            </td>
        </tr>
        @endforelse
    </tbody>
</table>
</div>


</x-filament::page>
