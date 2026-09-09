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
            <form id="paymentForm" action="{{ route('nota-bk.save-payment', $record) }}" method="POST">
                @csrf
                <input type="hidden" name="jenis" value="{{ $jenis }}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 bg-gray-50 p-5 rounded-lg border border-gray-200">
                    <div>
                        <label for="metode_pembayaran" class="block text-sm font-semibold text-gray-700 mb-1">
                            Metode Pembayaran <span class="text-red-500">*</span>
                        </label>
                        <select id="metode_pembayaran" name="metode_pembayaran" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white">
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer">Transfer</option>
                            <option value="Cek / Giro">Cek / Giro</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Pilihan metode pembayaran disimpan di browser Anda selama 1 tahun.</p>
                    </div>

                    <div id="rekeningWrapper" class="hidden">
                        <label for="id_rekening_perusahaan" class="block text-sm font-semibold text-gray-700 mb-1">
                            Pilih Rekening Perusahaan <span class="text-red-500">*</span>
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
                        <p class="text-xs text-gray-500 mt-1">Informasi rekening ini akan tercetak pada bagian Pembayaran nota.</p>
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-end gap-3">
                    <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Simpan & Cetak {{ $jenis === 'sales' ? 'Nota Sales' : 'Nota Kantor' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Ringkasan Item Nota yang akan dicetak -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">Daftar Barang (Item Nota)</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-semibold text-xs uppercase tracking-wider">
                            <th class="py-3 px-3 w-12 text-center">No</th>
                            <th class="py-3 px-3">Nama Barang</th>
                            <th class="py-3 px-3 text-center w-20">Satuan</th>
                            <th class="py-3 px-3 text-right w-20">Qty</th>
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
                                <td class="py-2.5 px-3 font-medium text-gray-900">{{ $item->nama_barang }}</td>
                                <td class="py-2.5 px-3 text-center text-gray-600">{{ $item->satuan }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-900">{{ number_format($item->qty) }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-900">{{ number_format($item->harga, 0, ',', '.') }}</td>
                                @if($jenis === 'sales')
                                <td class="py-2.5 px-3 text-right text-gray-500">0</td>
                                <td class="py-2.5 px-3 text-right text-gray-500">0</td>
                                @endif
                                <td class="py-2.5 px-3 text-right font-semibold text-gray-900">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $jenis === 'sales' ? '8' : '6' }}" class="py-6 text-center text-gray-400">
                                    Tidak ada item barang pada nota ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-bold text-gray-900 border-t-2 border-gray-200">
                            <td colspan="{{ $jenis === 'sales' ? '7' : '5' }}" class="py-3 px-3 text-right uppercase">Total:</td>
                            <td class="py-3 px-3 text-right text-indigo-700 text-base">{{ number_format($grandTotal, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
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