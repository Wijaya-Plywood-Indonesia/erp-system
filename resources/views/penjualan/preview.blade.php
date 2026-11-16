<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview {{ $jenis === 'sales' ? 'Nota Sales' : 'Nota Kantor' }} - {{ $record->no_nota }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen text-gray-800 font-sans p-4 sm:p-6 lg:p-8">
    <div class="max-w-4xl mx-auto">
        <!-- Card Navigasi / Header -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-4 mb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $jenis === 'sales' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                            {{ $jenis === 'sales' ? 'Nota Sales' : 'Nota Kantor' }}
                        </span>
                        <h1 class="text-xl font-bold text-gray-900">Preview & Pilihan Pembayaran</h1>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">No. Nota: <span class="font-semibold text-gray-700">{{ $record->no_nota }}</span> &bull; Kepada: <span class="font-semibold text-gray-700">{{ $record->tujuan_nota }}</span></p>
                </div>
                <div>
                    <a href="javascript:history.back()" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50">
                        &larr; Kembali
                    </a>
                </div>
            </div>

            <!-- Form Metode Pembayaran -->
            <form id="paymentForm" action="{{ route('penjualan.save-payment', $record) }}" method="POST">
                @csrf
                <input type="hidden" name="jenis" value="{{ $jenis }}">

                <div class="grid grid-cols-1 md:grid-cols-4 gap-5 bg-gray-50 p-5 rounded-lg border border-gray-200">
                    <div>
                        <label for="metode_pembayaran" class="block text-sm font-semibold text-gray-700 mb-1">
                            Metode Pembayaran <span class="text-red-500">*</span>
                        </label>
                        <select id="metode_pembayaran" name="metode_pembayaran" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white">
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer">Transfer</option>
                            <option value="Cek / Giro">Cek / Giro</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Pilihan disimpan 1 tahun.</p>
                    </div>

                    <div id="rekeningWrapper" class="hidden">
                        <label for="id_rekening_perusahaan" class="block text-sm font-semibold text-gray-700 mb-1">
                            Pilih Rekening <span class="text-red-500">*</span>
                        </label>
                        <select id="id_rekening_perusahaan" name="id_rekening_perusahaan" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white">
                            <option value="">-- Pilih Rekening --</option>
                            @foreach ($rekeningList as $rek)
                                <option value="{{ $rek->id }}"
                                    data-bank="{{ $rek->nama_bank }}"
                                    data-norek="{{ $rek->no_rekening }}"
                                    data-an="{{ $rek->atas_nama }}"
                                    {{ (string) $record->id_rekening_perusahaan === (string) $rek->id ? 'selected' : '' }}>
                                    {{ $rek->nama_bank }} - {{ $rek->no_rekening }} (a/n {{ $rek->atas_nama }})
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Dicetak di nota.</p>
                    </div>

                    <div class="flex flex-col justify-between">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            PPN 11%
                        </label>
                        <div class="flex items-center mt-2">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="pakai_ppn" name="pakai_ppn" value="1" {{ ($record->pakai_ppn ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 h-5 w-5">
                                <span class="ml-2 text-sm font-medium text-gray-800">Gunakan PPN (11%)</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Cetak Invoice Resmi jika aktif.</p>
                    </div>

                    <div>
                        <label for="ongkir" class="block text-sm font-semibold text-gray-700 mb-1">
                            Biaya Ongkir (Rp)
                        </label>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-semibold text-gray-500">Rp</span>
                            <input type="number" step="1" min="0" id="ongkir" name="ongkir" value="{{ (float) ($record->ongkir ?? 0) }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white" placeholder="0">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Biaya kirim/ekspedisi</p>
                    </div>
                </div>

                <!-- Bagian Pengusaha Kena Pajak & Pembeli BKP (Tampil jika PPN aktif) -->
                <div id="invoiceResmiFields" class="mt-4 p-4 bg-blue-50/50 rounded-lg border border-blue-200 {{ ($record->pakai_ppn ?? true) ? '' : 'hidden' }}">
                    <div class="text-sm font-bold text-blue-900 mb-3 flex items-center justify-between">
                        <span>Data Faktur / Invoice Resmi</span>
                        <span class="text-xs font-normal text-blue-700">*Auto-fill default perusahaan, dapat disesuaikan</span>
                    </div>

                    <div class="mb-3">
                        <label for="no_invoice_resmi" class="block text-xs font-semibold text-gray-700 mb-1">Nomor Invoice Resmi</label>
                        <input type="text" id="no_invoice_resmi" name="no_invoice_resmi" value="{{ $record->no_invoice_resmi ?? ('INV/WP/' . ($record->tanggal ? $record->tanggal->format('m/Y') : date('m/Y')) . '/' . $record->no_nota) }}" class="w-full text-xs rounded-md border-gray-300 shadow-sm py-1.5 px-3 bg-white border">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- PKP -->
                        <div class="bg-white p-3 rounded border border-gray-200">
                            <div class="font-bold text-xs uppercase text-gray-700 mb-2 border-b pb-1">Pengusaha Kena Pajak (Penjual)</div>
                            <div class="space-y-2">
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600">Nama PKP</label>
                                    <input type="text" name="pkp_nama" value="{{ $record->pkp_nama ?: 'EDDY ANGKAWIJAYA' }}" class="w-full text-xs rounded border-gray-300 py-1 px-2 border">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600">Alamat PKP</label>
                                    <textarea name="pkp_alamat" rows="2" class="w-full text-xs rounded border-gray-300 py-1 px-2 border">{{ $record->pkp_alamat ?: 'Jl.Dr. Cipto GG.Kalianyar Rt.005/Rw.001, Bedali Lawang Kab Malang Jawa Timur' }}</textarea>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600">N.P.W.P</label>
                                    <input type="text" name="pkp_npwp" value="{{ $record->pkp_npwp ?: '06.059.324.1-657.000' }}" class="w-full text-xs rounded border-gray-300 py-1 px-2 border">
                                </div>
                            </div>
                        </div>

                        <!-- Pembeli BKP -->
                        <div class="bg-white p-3 rounded border border-gray-200">
                            <div class="font-bold text-xs uppercase text-gray-700 mb-2 border-b pb-1">Pembeli BKP / Penerima JKP</div>
                            <div class="space-y-2">
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600">Nama Pembeli BKP</label>
                                    <input type="text" name="pembeli_bkp_nama" value="{{ $record->pembeli_bkp_nama ?: ($record->tujuan_nota ?: '') }}" class="w-full text-xs rounded border-gray-300 py-1 px-2 border">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600">Alamat Pembeli BKP</label>
                                    <textarea name="pembeli_bkp_alamat" rows="2" class="w-full text-xs rounded border-gray-300 py-1 px-2 border">{{ $record->pembeli_bkp_alamat ?: ($record->alamat ?: '') }}</textarea>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600">N.P.W.P Pembeli</label>
                                    <input type="text" name="pembeli_bkp_npwp" value="{{ $record->pembeli_bkp_npwp ?: '' }}" placeholder="00.000.000.0-000.000" class="w-full text-xs rounded border-gray-300 py-1 px-2 border">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-end gap-3">
                    <button type="submit" id="btnInvoiceResmi" name="cetak_action" value="invoice_resmi" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition {{ ($record->pakai_ppn ?? true) ? '' : 'hidden' }}">
                        Cetak Invoice Resmi
                    </button>
                    <button type="submit" name="cetak_action" value="nota" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                        Cetak Nota
                    </button>
                    <button type="submit" name="cetak_action" value="sj" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition">
                        Cetak SJ
                    </button>
                    <button type="submit" name="cetak_action" value="semua" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white bg-slate-800 hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition">
                        Cetak Semua (SJ & Nota)
                    </button>
                </div>
            </form>
        </div>

        <!-- Ringkasan Item Nota yang akan dicetak -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-900">Daftar Barang (Item Nota)</h2>
            </div>
            
            @if(session('success'))
                <div class="mb-4 bg-green-50 text-green-700 p-3 rounded-md text-sm border border-green-200">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('penjualan.update-items', $record) }}" method="POST">
                @csrf
                <input type="hidden" name="jenis" value="{{ $jenis }}">
                <div class="overflow-x-auto">
                    @php
                        $hasM3 = collect($items)->contains(fn($item) => $item->m3 !== null);
                    @endphp
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-semibold text-xs uppercase tracking-wider">
                                <th class="py-3 px-3 w-12 text-center">No</th>
                                <th class="py-3 px-3">Nama Barang</th>
                                <th class="py-3 px-3 text-center w-20">Satuan</th>
                                <th class="py-3 px-3 text-right w-20">Qty</th>
                                @if($hasM3)
                                <th class="py-3 px-3 text-right w-28 whitespace-nowrap">m3</th>
                                @endif
                                <th class="py-3 px-3 text-center w-24">Hitung</th>
                                <th class="py-3 px-3 text-right w-32">Harga</th>
                                @if($jenis === 'sales')
                                <th class="py-3 px-3 text-right w-24">Pot/pcs</th>
                                <th class="py-3 px-3 text-right w-24">Total Pot</th>
                                @endif
                                <th class="py-3 px-3 text-right w-36">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($items as $index => $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2.5 px-3 text-center text-gray-500">{{ $index + 1 }}</td>
                                    <td class="py-2.5 px-3 font-medium text-gray-900">
                                        @if(isset($item->id_detail) && $item->id_detail)
                                            <input type="text" name="items[{{ $item->id_detail }}][nama_barang]" value="{{ $item->nama_barang }}" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1">
                                        @else
                                            {{ $item->nama_barang }}
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-gray-600">{{ $item->satuan }}</td>
                                    <td class="py-2.5 px-3 text-right text-gray-900">{{ number_format($item->qty) }}</td>
                                    @if($hasM3)
                                    <td class="py-2.5 px-3 text-right text-gray-900 whitespace-nowrap">{{ $item->m3 !== null ? number_format($item->m3, 4, ',', '.') : '-' }}</td>
                                    @endif
                                    <td class="py-2.5 px-3 text-center">
                                        @if(isset($item->id_detail) && $item->id_detail)
                                            <select name="items[{{ $item->id_detail }}][hit_kbk]" class="text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1 px-1 bg-white">
                                                <option value="b" {{ ($item->hit_kbk ?? 'b') === 'b' ? 'selected' : '' }}>Qty (b)</option>
                                                <option value="m" {{ ($item->hit_kbk ?? 'b') === 'm' ? 'selected' : '' }}>m³ (m)</option>
                                            </select>
                                        @else
                                            <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-semibold uppercase">{{ $item->hit_kbk ?? 'b' }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right text-gray-900">
                                        @if(isset($item->id_detail) && $item->id_detail)
                                            <input type="text" name="items[{{ $item->id_detail }}][harga]" value="{{ number_format($item->harga, 0, ',', '.') }}" class="w-full text-right border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1">
                                        @else
                                            {{ number_format($item->harga, 0, ',', '.') }}
                                        @endif
                                    </td>
                                    @if($jenis === 'sales')
                                    <td class="py-2.5 px-3 text-right text-gray-500">
                                        @if(isset($item->id_detail) && $item->id_detail)
                                            <input type="text" name="items[{{ $item->id_detail }}][potongan]" value="{{ number_format($item->potongan ?? 0, 0, ',', '.') }}" class="w-full text-right border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1">
                                        @else
                                            {{ number_format($item->potongan ?? 0, 0, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right text-gray-500">
                                        {{ number_format(($item->total_pot ?? 0), 0, ',', '.') }}
                                    </td>
                                    @endif
                                    <td class="py-2.5 px-3 text-right font-semibold text-gray-900">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    @php
                                        $colCount = $jenis === 'sales' ? ($hasM3 ? 10 : 9) : ($hasM3 ? 8 : 7);
                                    @endphp
                                    <td colspan="{{ $colCount }}" class="py-6 text-center text-gray-400">
                                        Tidak ada item barang pada nota ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            @php
                                $footColCount = $jenis === 'sales' ? ($hasM3 ? 9 : 8) : ($hasM3 ? 7 : 6);
                                $pakaiPpnVal = $record->pakai_ppn ?? true;
                                $ppnPersenVal = $pakaiPpnVal ? (float) ($record->ppn_persen ?: 11) : 0;
                                $ppnNominalVal = round(($grandTotal * $ppnPersenVal) / 100, 2);
                                $ongkirVal = (float) ($record->ongkir ?? 0);
                                $totalWithPpn = $grandTotal + $ppnNominalVal + $ongkirVal;
                            @endphp
                            <tr class="bg-gray-50 font-bold text-gray-700 border-t-2 border-gray-200">
                                <td colspan="{{ $footColCount }}" class="py-2.5 px-3 text-right uppercase text-xs">Subtotal:</td>
                                <td class="py-2.5 px-3 text-right text-gray-900">{{ number_format($grandTotal, 0, ',', '.') }}</td>
                            </tr>
                            @if($ppnPersenVal > 0)
                            <tr class="bg-gray-50 font-semibold text-gray-600 border-t border-gray-200">
                                <td colspan="{{ $footColCount }}" class="py-2 px-3 text-right text-xs">PPN ({{ $ppnPersenVal }}%):</td>
                                <td class="py-2 px-3 text-right text-emerald-700">+ {{ number_format($ppnNominalVal, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            @if($ongkirVal > 0)
                            <tr class="bg-gray-50 font-semibold text-gray-600 border-t border-gray-200">
                                <td colspan="{{ $footColCount }}" class="py-2 px-3 text-right text-xs">Ongkir:</td>
                                <td class="py-2 px-3 text-right text-blue-700">+ {{ number_format($ongkirVal, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            @if($ppnPersenVal > 0 || $ongkirVal > 0)
                            <tr class="bg-indigo-50 font-extrabold text-gray-900 border-t-2 border-indigo-200">
                                <td colspan="{{ $footColCount }}" class="py-3 px-3 text-right uppercase text-sm text-indigo-900">Total Akhir:</td>
                                <td class="py-3 px-3 text-right text-indigo-700 text-base">{{ number_format($totalWithPpn, 0, ',', '.') }}</td>
                            </tr>
                            @else
                            <tr class="bg-gray-50 font-bold text-gray-900 border-t-2 border-gray-200">
                                <td colspan="{{ $footColCount }}" class="py-3 px-3 text-right uppercase">Total:</td>
                                <td class="py-3 px-3 text-right text-indigo-700 text-base">{{ number_format($grandTotal, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
                
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="if(confirm('Apakah Anda yakin ingin mengembalikan semua data nama & harga ke nilai default?')) { document.getElementById('resetForm').submit(); }" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 font-medium py-2 px-4 rounded-md shadow-sm text-sm transition duration-150 ease-in-out">
                        Reset ke Default
                    </button>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md shadow-sm text-sm transition duration-150 ease-in-out">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
            
            <form id="resetForm" action="{{ route('penjualan.reset-items', $record) }}" method="POST" class="hidden">
                @csrf
            </form>
        </div>
    </div>

    <!-- Script localStorage 1 Tahun -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const STORAGE_KEY = 'nota_metode_pembayaran_pref';
            const ONE_YEAR_MS = 365 * 24 * 60 * 60 * 1000;

            const selectMetode = document.getElementById('metode_pembayaran');
            const selectRekening = document.getElementById('id_rekening_perusahaan');
            const rekeningWrapper = document.getElementById('rekeningWrapper');
            const paymentForm = document.getElementById('paymentForm');
            const checkboxPpn = document.getElementById('pakai_ppn');
            const invoiceResmiFields = document.getElementById('invoiceResmiFields');
            const btnInvoiceResmi = document.getElementById('btnInvoiceResmi');

            if (checkboxPpn) {
                checkboxPpn.addEventListener('change', function () {
                    if (this.checked) {
                        invoiceResmiFields.classList.remove('hidden');
                        btnInvoiceResmi.classList.remove('hidden');
                    } else {
                        invoiceResmiFields.classList.add('hidden');
                        btnInvoiceResmi.classList.add('hidden');
                    }
                });
            }

            function toggleRekening(metode) {
                if (metode === 'Transfer') {
                    rekeningWrapper.classList.remove('hidden');
                    selectRekening.setAttribute('required', 'required');
                } else {
                    rekeningWrapper.classList.add('hidden');
                    selectRekening.removeAttribute('required');
                }
            }

            // 1. Baca dari localStorage dengan kontrol expiry 1 tahun
            let savedMetode = 'Tunai';
            let savedRekeningId = '';

            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (raw) {
                    const parsed = JSON.parse(raw);
                    const now = new Date().getTime();
                    // Cek masa berlaku 1 tahun
                    if (parsed && parsed.expiry && now < parsed.expiry) {
                        if (parsed.metode) savedMetode = parsed.metode;
                        if (parsed.rekeningId) savedRekeningId = parsed.rekeningId;
                    } else {
                        // Expired -> hapus
                        localStorage.removeItem(STORAGE_KEY);
                    }
                }
            } catch (e) {
                console.error('Gagal membaca localStorage', e);
            }

            // Utamakan data yang sudah ada di record database jika sudah tersimpan sebelumnya
            const existingRecordMetode = @json($record->metode_pembayaran);
            const existingRecordRekening = @json($record->id_rekening_perusahaan);

            const initialMetode = existingRecordMetode || savedMetode || 'Tunai';
            selectMetode.value = initialMetode;

            if (existingRecordRekening) {
                selectRekening.value = existingRecordRekening;
            } else if (savedRekeningId && selectRekening.querySelector(`option[value="${savedRekeningId}"]`)) {
                selectRekening.value = savedRekeningId;
            }

            toggleRekening(selectMetode.value);

            // Listener saat metode pembayaran diganti
            selectMetode.addEventListener('change', function () {
                toggleRekening(this.value);
            });

            // Simpan ke localStorage saat form disubmit
            paymentForm.addEventListener('submit', function (e) {
                const metode = selectMetode.value;
                const rekeningId = selectRekening.value;
                const now = new Date().getTime();

                const payload = {
                    metode: metode,
                    rekeningId: rekeningId,
                    expiry: now + ONE_YEAR_MS,
                    savedAt: now
                };

                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
                } catch (err) {
                    console.error('Gagal menyimpan ke localStorage', err);
                }
            });
        });
    </script>
</body>
</html>