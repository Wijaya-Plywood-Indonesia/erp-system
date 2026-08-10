<div>
    @if ($this->wajibAbsen)
        <div
            x-data
            x-init="document.body.style.overflow = 'hidden'"
            x-on:livewire:navigated.window="if (!@js($this->wajibAbsen)) { document.body.style.overflow = ''; }"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-sm"
            {{-- Tidak ada @click di backdrop -> tidak bisa ditutup dengan klik di luar modal --}}
        >
            <div
                class="w-full max-w-lg mx-4 rounded-xl bg-gray-900 border border-gray-700 shadow-2xl p-6"
                {{-- Stop propagation supaya klik di dalam modal tidak ikut ke backdrop --}}
                x-on:click.stop
            >
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-white">🔒 Absen Dulu, Baru Boleh Lanjut!</h2>
                    <p class="text-sm text-gray-400 mt-1">
                        Nama Anda belum tercatat di menu Lain Lain hari ini ({{ \Illuminate\Support\Carbon::today()->translatedFormat('d F Y') }}).
                        Menu-menu lain akan tetap terkunci sampai form di bawah ini disubmit — jadi jangan coba-coba cari tombol close ya, tidak ada. 😄
                    </p>
                </div>

                <form wire:submit="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-300 mb-1">Jam Masuk <span class="text-red-500">*</span></label>
                            <select
                                wire:model="masuk"
                                class="w-full rounded-lg bg-gray-800 border-gray-700 text-white text-sm focus:border-amber-500 focus:ring-amber-500"
                            >
                                @foreach ($this->timeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('masuk') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-300 mb-1">Jam Pulang</label>
                            <select
                                wire:model="pulang"
                                class="w-full rounded-lg bg-gray-800 border-gray-700 text-white text-sm focus:border-amber-500 focus:ring-amber-500"
                            >
                                <option value="">-- Belum Pulang --</option>
                                @foreach ($this->timeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('pulang') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Pegawai <span class="text-red-500">*</span></label>
                        <select
                            wire:model="id_pegawai"
                            class="w-full rounded-lg bg-gray-800 border-gray-700 text-white text-sm focus:border-amber-500 focus:ring-amber-500"
                        >
                            <option value="">-- Pilih Pegawai --</option>
                            @foreach ($this->pegawais as $pegawai)
                                <option value="{{ $pegawai->id }}">
                                @if ($pegawai->kode_pegawai && $pegawai->nama_pegawai)
                                    {{ $pegawai->kode_pegawai }} - {{ $pegawai->nama_pegawai }}
                                @else
                                    {{ $pegawai->nama_pegawai ?: $pegawai->kode_pegawai ?: "Pegawai #{$pegawai->id}" }}
                                @endif
                            </option>
                            @endforeach
                        </select>
                        @error('id_pegawai') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-gray-500 mt-1">Pilih diri Anda sendiri, atau pegawai lain jika mengabsenkan izin/tidak masuk.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Ijin</label>
                        <input
                            type="text"
                            wire:model="ijin"
                            placeholder="Kosongkan jika masuk normal"
                            class="w-full rounded-lg bg-gray-800 border-gray-700 text-white text-sm focus:border-amber-500 focus:ring-amber-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-300 mb-1">Keterangan</label>
                            <textarea
                                wire:model="ket"
                                rows="2"
                                class="w-full rounded-lg bg-gray-800 border-gray-700 text-white text-sm focus:border-amber-500 focus:ring-amber-500"
                            ></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-300 mb-1">Hasil</label>
                            <textarea
                                wire:model="hasil"
                                rows="2"
                                class="w-full rounded-lg bg-gray-800 border-gray-700 text-white text-sm focus:border-amber-500 focus:ring-amber-500"
                            ></textarea>
                        </div>
                    </div>

                    <div class="pt-2 space-y-2">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="submit"
                            class="w-full inline-flex justify-center items-center gap-2 rounded-lg bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-gray-900 font-semibold text-sm px-4 py-2.5 transition"
                        >
                            <span wire:loading.remove wire:target="submit">Submit Absen</span>
                            <span wire:loading wire:target="submit">Menyimpan...</span>
                        </button>

                        <button
                            type="button"
                            wire:click="logout"
                            wire:confirm="Anda akan keluar dari akun tanpa mengisi absen. Lanjutkan logout?"
                            class="w-full text-center text-xs text-gray-500 hover:text-gray-300 underline underline-offset-2 transition"
                        >
                            Salah akun? Logout di sini
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>