<?php

namespace App\Providers;

use App\Models\ModalSanding;
use App\Observers\ModalSandingObserver;
use Illuminate\Support\Facades\DB;
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
        // ⬇⬇⬇  PASANGKAN DI SINI, PALING ATAS DALAM boot()  ⬇⬇⬇
        DB::statement("SET time_zone = '+07:00'");
        // ⬆⬆⬆  WAJIB: supaya MySQL ikut Asia/Jakarta
        ModalSanding::observe(ModalSandingObserver::class);
        RencanaKerjaHp::observe(RencanaKerjaHpObserver::class);

        if (app()->environment('local')) {
            // Kita paksa sistem menganggap hari ini adalah Kamis, 12 Feb 2026
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 2, 12, 9, 0, 0));
        }
        // PlatformHasilHp::observe(PlatformHasilHpObserver::class);
        // TriplekHasilHp::observe(TriplekHasilHpObserver::class);

        FilamentView::registerRenderHook(
            'panels::body.end',
            fn(): string => Blade::render(<<<'HTML'
                
                <!-- Load Library LocalForage -->
                <script src="https://cdnjs.cloudflare.com/ajax/libs/localforage/1.10.0/localforage.min.js"></script>

                <script>
                    /**
                     * ==========================================
                     * 1. LOGIC UNTUK DETAIL KAYU MASUK (LAMA)
                     * ==========================================
                     */
                    window.offlineDetailLogic = function(config) {
                        return {
                            online: navigator.onLine,
                            isSyncing: false,
                            pendingItems: [],
                            
                            form: {
                                id_lahan: localStorage.getItem('sticky_lahan_' + config.parentId) || config.lahanDefault || '',
                                id_jenis_kayu: localStorage.getItem('sticky_jenis_' + config.parentId) || config.jenisDefault || '',
                                panjang: localStorage.getItem('sticky_panjang_' + config.parentId) || '130',
                                grade: localStorage.getItem('sticky_grade_' + config.parentId) || '1',
                                jumlah_batang: localStorage.getItem('sticky_jumlah_' + config.parentId) || '1',
                                diameter: ''
                            },
                            
                            storageKey: 'offline_kayu_masuk_' + config.parentId,

                            init() {
                                localforage.config({ name: 'AppKayuOffline' });
                                this.loadStorage();
                                window.addEventListener('online', () => this.online = true);
                                window.addEventListener('offline', () => this.online = false);
                                this.$nextTick(() => { if(this.$refs.diameterInput) this.$refs.diameterInput.focus(); });
                            },

                            async loadStorage() {
                                this.pendingItems = await localforage.getItem(this.storageKey) || [];
                            },

                            async removeItem(index) {
                                if(!confirm('Hapus data ini dari draft?')) return;
                                this.pendingItems.splice(index, 1);
                                await localforage.setItem(this.storageKey, JSON.parse(JSON.stringify(this.pendingItems)));
                                new FilamentNotification().title('Data dihapus').success().send();
                            },

                            async create(closeAfter = true) {
                                if (!this.form.id_lahan || !this.form.id_jenis_kayu || !this.form.diameter || !this.form.jumlah_batang) {
                                    new FilamentNotification().title('Data belum lengkap!').danger().send(); return;
                                }

                                localStorage.setItem('sticky_lahan_' + config.parentId, this.form.id_lahan);
                                localStorage.setItem('sticky_jenis_' + config.parentId, this.form.id_jenis_kayu);
                                localStorage.setItem('sticky_panjang_' + config.parentId, this.form.panjang);
                                localStorage.setItem('sticky_grade_' + config.parentId, this.form.grade);
                                localStorage.setItem('sticky_jumlah_' + config.parentId, this.form.jumlah_batang);

                                const newItem = JSON.parse(JSON.stringify(this.form));
                                this.pendingItems.unshift(newItem);
                                await localforage.setItem(this.storageKey, JSON.parse(JSON.stringify(this.pendingItems)));
                                
                                new FilamentNotification().title('Tersimpan (Offline)').success().send();

                                if (closeAfter) {
                                    this.$dispatch('close-modal', { id: 'modal-offline-input' });
                                } else {
                                    this.form.diameter = '';
                                    this.$nextTick(() => { if (this.$refs.diameterInput) this.$refs.diameterInput.focus(); });
                                }
                            },

                            async syncNow() {
                                this.isSyncing = true;
                                try {
                                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                                    const payloadItems = JSON.parse(JSON.stringify(this.pendingItems));

                                    // URL: KAYU MASUK
                                    const res = await fetch('/api/offline/sync-detail-kayu-masuk', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                                        body: JSON.stringify({ parent_id: config.parentId, items: payloadItems })
                                    });

                                    const data = await res.json();
                                    if (res.ok) {
                                        this.pendingItems = [];
                                        await localforage.removeItem(this.storageKey);
                                        Livewire.dispatch('refreshDatatable'); 
                                        new FilamentNotification().title('Berhasil Sinkronisasi Kayu Masuk').success().send();
                                    } else { throw new Error(data.message || 'Gagal Sync'); }
                                } catch (e) {
                                    new FilamentNotification().title('Gagal Upload: ' + e.message).danger().send();
                                } finally { this.isSyncing = false; }
                            }
                        };
                    };

                    /**
                     * ==========================================
                     * 2. LOGIC UNTUK TURUSAN KAYU (BARU & DIPERBAIKI)
                     * ==========================================
                     */
                    window.offlineTurusanLogic = function(config) {
                        return {
                            online: navigator.onLine,
                            isSyncing: false,
                            pendingItems: [],
                            
                            // Nama field disesuaikan dengan view offline-turusan-modal.blade.php
                            form: {
                                lahan_id: localStorage.getItem('sticky_turus_lahan_' + config.parentId) || config.lahanDefault || '',
                                jenis_kayu_id: localStorage.getItem('sticky_turus_jenis_' + config.parentId) || config.jenisDefault || '',
                                panjang: localStorage.getItem('sticky_turus_panjang_' + config.parentId) || '130',
                                grade: localStorage.getItem('sticky_turus_grade_' + config.parentId) || '1',
                                kuantitas: '1',
                                diameter: ''
                            },
                            
                            storageKey: 'offline_turusan_' + config.parentId,

                            init() {
                                localforage.config({ name: 'AppKayuOffline' });
                                this.loadStorage();
                                window.addEventListener('online', () => this.online = true);
                                window.addEventListener('offline', () => this.online = false);
                                this.$nextTick(() => { if(this.$refs.diameterInput) this.$refs.diameterInput.focus(); });
                            },

                            async loadStorage() {
                                this.pendingItems = await localforage.getItem(this.storageKey) || [];
                            },

                            async removeItem(index) {
                                if(!confirm('Hapus data ini dari draft?')) return;
                                this.pendingItems.splice(index, 1);
                                await localforage.setItem(this.storageKey, JSON.parse(JSON.stringify(this.pendingItems)));
                                new FilamentNotification().title('Data dihapus').success().send();
                            },

                            async create(closeAfter = true) {
                                if (!this.form.lahan_id || !this.form.jenis_kayu_id || !this.form.diameter) {
                                    new FilamentNotification().title('Data belum lengkap!').danger().send(); return;
                                }

                                // Sticky khusus Turusan (Key berbeda agar tidak bentrok)
                                localStorage.setItem('sticky_turus_lahan_' + config.parentId, this.form.lahan_id);
                                localStorage.setItem('sticky_turus_jenis_' + config.parentId, this.form.jenis_kayu_id);
                                localStorage.setItem('sticky_turus_panjang_' + config.parentId, this.form.panjang);
                                localStorage.setItem('sticky_turus_grade_' + config.parentId, this.form.grade);

                                const newItem = JSON.parse(JSON.stringify(this.form));
                                this.pendingItems.unshift(newItem);
                                await localforage.setItem(this.storageKey, JSON.parse(JSON.stringify(this.pendingItems)));
                                
                                new FilamentNotification().title('Turusan Tersimpan (Offline)').success().send();

                                if (closeAfter) {
                                    this.$dispatch('close-modal', { id: 'modal-offline-turusan' });
                                } else {
                                    this.form.diameter = '';
                                    this.$nextTick(() => { if (this.$refs.diameterInput) this.$refs.diameterInput.focus(); });
                                }
                            },

                            async syncNow() {
                                this.isSyncing = true;
                                try {
                                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                                    const payloadItems = JSON.parse(JSON.stringify(this.pendingItems));

                                    // URL: TURUSAN (Sudah Diperbaiki)
                                    const res = await fetch('/api/offline/sync-detail-turusan-kayu', {
                                        method: 'POST',
                                        headers: { 
                                            'Content-Type': 'application/json', 
                                            'Accept': 'application/json', 
                                            'X-CSRF-TOKEN': csrfToken 
                                        },
                                        body: JSON.stringify({ parent_id: config.parentId, items: payloadItems })
                                    });

                                    const data = await res.json();
                                    if (res.ok) {
                                        this.pendingItems = [];
                                        await localforage.removeItem(this.storageKey);
                                        Livewire.dispatch('refreshDatatable'); 
                                        new FilamentNotification().title('Berhasil Sinkronisasi Turusan').success().send();
                                    } else { 
                                        throw new Error(data.message || 'Gagal Sync'); 
                                    }
                                } catch (e) {
                                    console.error(e);
                                    new FilamentNotification().title('Gagal: ' + e.message).danger().send();
                                } finally { 
                                    this.isSyncing = false; 
                                }
                            }
                        };
                    };
                </script>
            HTML)
        );
    }
}
