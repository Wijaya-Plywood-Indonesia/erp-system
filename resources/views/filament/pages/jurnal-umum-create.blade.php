<x-filament::page>

{{-- ================= FORM ================= --}}
<h2 class="text-xl font-bold mb-4">Buat Jurnal Umum</h2>

{{-- HEADER --}}
<div class="grid grid-cols-3 gap-4 mb-4">
    <x-filament::input type="date" wire:model="header.tgl" label="Tanggal" />
    <x-filament::input wire:model="header.kode_jurnal" label="Kode Jurnal" readonly />
    <x-filament::input wire:model="header.no_dokumen" label="No Dokumen" />
</div>

{{-- TABLE FORM --}}
<div class="overflow-x-auto mb-4">
<table class="w-full text-sm">
    <thead>
        <tr>
            <th>No Akun</th>
            <th>Keterangan</th>
            <th>Debit</th>
            <th>Kredit</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $i => $row)
        <tr>
            <td>
                <select wire:model="rows.{{ $i }}.no_akun">
                    <option value="">-- pilih --</option>
                    @foreach(\App\Helpers\AkunHelper::debitAccounts() as $k=>$v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </td>

            <td>
                <input wire:model="rows.{{ $i }}.keterangan">
            </td>

            <td>
                <input type="number" wire:model.live="rows.{{ $i }}.debit"
                    @disabled($row['kredit'] > 0)>
            </td>

            <td>
                <input type="number" wire:model.live="rows.{{ $i }}.kredit"
                    @disabled($row['debit'] > 0)>
            </td>

            <td>
                <button wire:click="removeRow({{ $i }})">❌</button>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
</div>

<div class="flex justify-between mb-6">
    <x-filament::button wire:click="addRow">+ Baris</x-filament::button>

    <div class="text-right">
        <div>Debit: Rp {{ number_format($this->total_debit) }}</div>
        <div>Kredit: Rp {{ number_format($this->total_kredit) }}</div>
        <div>
            {!! $this->isBalance()
                ? '✅ BALANCE'
                : '❌ SELISIH Rp '.number_format(abs($this->total_debit - $this->total_kredit)) !!}
        </div>
    </div>
</div>

<x-filament::button wire:click="save" :disabled="!$this->isBalance()">
    Simpan Jurnal
</x-filament::button>

<hr class="my-10">

{{-- ================= LIST ================= --}}
<h2 class="text-xl font-bold mb-4">Daftar Jurnal Umum</h2>

<div class="overflow-x-auto">
<table class="w-full text-sm">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>No Akun</th>
            <th>Keterangan</th>
            <th>Debit</th>
            <th>Kredit</th>
        </tr>
    </thead>
    <tbody>
        @foreach($this->jurnals as $j)
        <tr>
            <td>{{ $j->tgl }}</td>
            <td>{{ $j->no_akun }}</td>
            <td>{{ $j->keterangan }}</td>
            <td>{{ $j->map === 'D' ? number_format($j->jumlah) : '' }}</td>
            <td>{{ $j->map === 'K' ? number_format($j->jumlah) : '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
</div>

</x-filament::page>
