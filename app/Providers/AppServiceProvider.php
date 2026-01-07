<?php

namespace App\Providers;

use App\Models\ModalSanding;
use App\Observers\ModalSandingObserver;
use Illuminate\Support\ServiceProvider;
use App\Models\RencanaKerjaHp;
use App\Observers\RencanaKerjaHpObserver;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ModalSanding::observe(ModalSandingObserver::class);
        RencanaKerjaHp::observe(RencanaKerjaHpObserver::class);
        // PlatformHasilHp::observe(PlatformHasilHpObserver::class);
        // TriplekHasilHp::observe(TriplekHasilHpObserver::class);

        FilamentView::registerRenderHook(
            'panels::body.end',
            fn(): string => Blade::render(<<<'HTML'
                
                <script src="https://cdnjs.cloudflare.com/ajax/libs/localforage/1.10.0/localforage.min.js"></script>

                <script>
                    window.offlineDetailLogic = function(config) {
                        return {
                            online: navigator.onLine,
                            isSyncing: false,
                            pendingItems: [],
                            form: {
                                // 1. Prioritas: Ambil dari Memory Browser (Sticky) -> Kalau tidak ada, ambil Default PHP -> Kalau tidak ada, kosong
                                id_lahan: localStorage.getItem('sticky_lahan_' + config.parentId) || config.lahanDefault || '',
                                id_jenis_kayu: localStorage.getItem('sticky_jenis_' + config.parentId) || config.jenisDefault || '', 
                                
                                // Default Panjang & Grade biasanya sama terus
                                panjang: localStorage.getItem('sticky_panjang_' + config.parentId) || '130',
                                grade: localStorage.getItem('sticky_grade_' + config.parentId) || '1',
                                
                                diameter: '', // Ini selalu kosong di awal
                                jumlah_batang: 1
                            },
                            storageKey: 'offline_kayu_masuk_' + config.parentId,

                            init() {
                                localforage.config({ name: 'AppKayuOffline' });
                                this.loadStorage();
                                window.addEventListener('online', () => this.online = true);
                                window.addEventListener('offline', () => this.online = false);
                            },

                            async loadStorage() {
                                this.pendingItems = await localforage.getItem(this.storageKey) || [];
                            },

                            async create(closeAfter = true) {
                                // Validasi
                                if (!this.form.id_lahan || !this.form.id_jenis_kayu || !this.form.diameter || !this.form.jumlah_batang) {
                                    new FilamentNotification().title('Data belum lengkap!').danger().send();
                                    return;
                                }

                                // 1. Simpan Pilihan Terakhir ke Memory Browser (Agar Sticky)
                                localStorage.setItem('sticky_lahan_' + config.parentId, this.form.id_lahan);
                                localStorage.setItem('sticky_jenis_' + config.parentId, this.form.id_jenis_kayu);
                                localStorage.setItem('sticky_panjang_' + config.parentId, this.form.panjang);
                                localStorage.setItem('sticky_grade_' + config.parentId, this.form.grade);

                                // 2. Bersihkan Object (Hapus Proxy Alpine)
                                const cleanItem = JSON.parse(JSON.stringify(this.form));

                                // 3. Masukkan ke Antrian
                                this.pendingItems.unshift(cleanItem);
                                
                                // 4. Simpan ke Database Offline
                                const cleanArray = JSON.parse(JSON.stringify(this.pendingItems));
                                await localforage.setItem(this.storageKey, cleanArray);

                                new FilamentNotification().title('Tersimpan (Offline)').success().send();

                                if (closeAfter) {
                                    this.$dispatch('close-modal', { id: 'modal-offline-input' });
                                } else {
                                    // === LOGIC CREATE ANOTHER ===
                                    
                                    // Kita HANYA mereset Diameter dan Jumlah Batang.
                                    // Lahan, Jenis, Panjang, Grade TIDAK DIUBAH (Tetap terpilih).
                                    this.form.diameter = '';
                                    this.form.jumlah_batang = 1; 

                                    // Fokus otomatis langsung ke input diameter
                                    // Jadi user tinggal ketik angka diameter -> Enter -> Simpan
                                    this.$nextTick(() => {
                                        if (this.$refs.diameterInput) {
                                            this.$refs.diameterInput.focus();
                                        }
                                    });
                                }
                            },

                            async syncNow() {
                                this.isSyncing = true;
                                try {
                                    const token = document.querySelector('meta[name="csrf-token"]')?.content;
                                    const payloadItems = JSON.parse(JSON.stringify(this.pendingItems));

                                    const res = await fetch('/api/offline/sync-detail-kayu-masuk', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': token
                                        },
                                        body: JSON.stringify({
                                            parent_id: config.parentId,
                                            items: payloadItems
                                        })
                                    });

                                    if (!res.ok) throw new Error('Gagal sinkronisasi');

                                    this.pendingItems = [];
                                    await localforage.removeItem(this.storageKey);
                                    Livewire.dispatch('refreshDatatable');

                                    new FilamentNotification().title('Sinkronisasi Berhasil').success().send();
                                } catch (e) {
                                    new FilamentNotification().title(e.message).danger().send();
                                } finally {
                                    this.isSyncing = false;
                                }
                            }
                        }
                    }
                </script>
            HTML)
        );
    }
}
