window.offlineTurusanLogicV2 = function (cfg) {
  const STORAGE_KEY = "turusan_offline_queue_v1";

  const newUuid = () => {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
      const r = (Math.random() * 16) | 0;
      return (c === "x" ? r : (r & 3) | 8).toString(16);
    });
  };

  const csrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.content || "";

  return {
    parentId: String(cfg.parentId),
    labels: cfg.labels,
    online: navigator.onLine,
    isSyncing: false,
    allItems: [],
    editingUuid: null,
    notice: "",
    noticeType: "info",
    form: {},

    init() {
      this.resetForm(true);
      this.load();

      window.addEventListener("online", () => (this.online = true));
      window.addEventListener("offline", () => (this.online = false));
      window.addEventListener("storage", (e) => {
        if (e.key === STORAGE_KEY) this.load();
      });

      if (navigator.storage && navigator.storage.persist) {
        navigator.storage.persist().catch(() => {});
      }

      this.migrateLegacy();
    },

    // Pindahkan data dari versi lama (localforage 'offline_turusan_<id>')
    async migrateLegacy() {
      if (typeof localforage === "undefined") return;
      try {
        localforage.config({ name: "AppKayuOffline" });
        const key = "offline_turusan_" + this.parentId;
        const old = await localforage.getItem(key);
        if (!Array.isArray(old) || old.length === 0) return;

        await localforage.setItem(key + "_backup", old);

        let moved = 0;
        for (const it of old) {
          if (!it || !(Number(it.diameter) > 0)) continue;
          this.allItems.push({
            uuid: newUuid(),
            parentId: this.parentId,
            lahan_id: String(it.lahan_id),
            jenis_kayu_id: String(it.jenis_kayu_id),
            panjang: String(it.panjang ?? "130"),
            grade: String(it.grade ?? "1"),
            diameter: Number(it.diameter),
            kuantitas: Math.max(1, Number(it.kuantitas) || 1),
            created_at: new Date().toISOString(),
            error: null,
          });
          moved++;
        }

        if (this.persist()) {
          await localforage.removeItem(key);
          if (moved)
            this.say(`${moved} data lama dipindahkan ke antrean.`, "success");
        }
      } catch (e) {
        console.warn("Migrasi data lama gagal", e);
      }
    },

    get pendingItems() {
      return this.allItems.filter((i) => String(i.parentId) === this.parentId);
    },
    get otherCount() {
      return this.allItems.length - this.pendingItems.length;
    },
    get totalBatang() {
      return this.pendingItems.reduce(
        (s, i) => s + Number(i.kuantitas || 0),
        0,
      );
    },

    load() {
      try {
        this.allItems = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
      } catch (e) {
        this.allItems = [];
      }
    },
    persist() {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(this.allItems));
        return true;
      } catch (e) {
        this.say(
          "Penyimpanan perangkat penuh atau diblokir. Data TIDAK tersimpan!",
          "error",
        );
        return false;
      }
    },

    resetForm(full = false) {
      const keep = full ? {} : this.form;
      this.form = {
        lahan_id: keep.lahan_id ?? String(cfg.lahanDefault),
        jenis_kayu_id: keep.jenis_kayu_id ?? String(cfg.jenisDefault),
        panjang: keep.panjang ?? "130",
        grade: keep.grade ?? "1",
        kuantitas: 1,
        diameter: "",
      };
    },

    say(msg, type = "info") {
      this.notice = msg;
      this.noticeType = type;
    },

    closeModal() {
      try {
        this.$wire.unmountAction();
      } catch (e) {
        console.warn("Gagal menutup modal lewat unmountAction", e);
      }
    },

    create(closeAfter) {
      const diameter = parseFloat(this.form.diameter);
      const qty = parseInt(this.form.kuantitas, 10);

      if (!(diameter > 0))
        return this.say("Diameter wajib diisi (lebih dari 0).", "error");
      if (!(qty >= 1)) return this.say("Kuantitas minimal 1.", "error");

      const payload = {
        uuid: this.editingUuid || newUuid(),
        parentId: this.parentId,
        lahan_id: String(this.form.lahan_id),
        jenis_kayu_id: String(this.form.jenis_kayu_id),
        panjang: String(this.form.panjang),
        grade: String(this.form.grade),
        diameter: diameter,
        kuantitas: qty,
        created_at: new Date().toISOString(),
        error: null,
      };

      if (this.editingUuid) {
        const idx = this.allItems.findIndex((i) => i.uuid === this.editingUuid);
        if (idx >= 0) {
          payload.created_at = this.allItems[idx].created_at;
          this.allItems.splice(idx, 1, payload);
        } else {
          this.allItems.push(payload);
        }
      } else {
        this.allItems.push(payload);
      }

      if (!this.persist()) return;

      this.editingUuid = null;
      this.say("Tersimpan di perangkat ini.", "success");

      if (closeAfter) {
        this.closeModal();
      } else {
        this.resetForm(false);
        this.$nextTick(() => this.$refs.diameterInput?.focus());
      }
    },

    edit(item) {
      this.editingUuid = item.uuid;
      this.form = {
        lahan_id: String(item.lahan_id),
        jenis_kayu_id: String(item.jenis_kayu_id),
        panjang: String(item.panjang),
        grade: String(item.grade),
        kuantitas: item.kuantitas,
        diameter: item.diameter,
      };
      this.$nextTick(() => this.$refs.diameterInput?.focus());
    },

    cancelEdit() {
      this.editingUuid = null;
      this.resetForm(false);
      this.form.diameter = "";
    },

    remove(item) {
      if (!confirm("Hapus data ini dari perangkat? Tidak bisa dikembalikan."))
        return;
      this.allItems = this.allItems.filter((i) => i.uuid !== item.uuid);
      this.persist();
    },

    clearAll() {
      if (
        !confirm(
          `Hapus ${this.pendingItems.length} data offline nota ini dari perangkat? Pastikan sudah di-download/diupload.`,
        )
      )
        return;
      this.allItems = this.allItems.filter(
        (i) => String(i.parentId) !== this.parentId,
      );
      this.persist();
    },

    async syncNow(onlyItem = null) {
      if (this.isSyncing) return;
      if (!navigator.onLine)
        return this.say("Tidak ada koneksi internet.", "error");

      // urutkan sesuai waktu input agar nomer_urut berurutan
      const batch = onlyItem
        ? [onlyItem]
        : [...this.pendingItems].sort((a, b) =>
            String(a.created_at).localeCompare(String(b.created_at)),
          );
      if (!batch.length) return;

      this.isSyncing = true;
      this.say("Mengupload...", "info");

      try {
        const res = await fetch(cfg.syncUrl, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrf(),
            "X-Requested-With": "XMLHttpRequest",
          },
          body: JSON.stringify({ items: batch }),
        });

        if (res.status === 401 || res.status === 419) {
          this.say(
            "Sesi login habis. Login ulang lalu coba upload lagi. Data tetap aman di perangkat.",
            "error",
          );
          return;
        }
        if (!res.ok) {
          this.say(
            `Server menolak (kode ${res.status}). Data tetap aman di perangkat.`,
            "error",
          );
          return;
        }

        const { results } = await res.json();
        const okIds = new Set(results.filter((r) => r.ok).map((r) => r.uuid));
        const errMap = Object.fromEntries(
          results.filter((r) => !r.ok).map((r) => [r.uuid, r.error]),
        );

        this.allItems = this.allItems
          .filter((i) => !okIds.has(i.uuid))
          .map((i) => (errMap[i.uuid] ? { ...i, error: errMap[i.uuid] } : i));
        this.persist();

        const gagal = Object.keys(errMap).length;
        this.say(
          gagal
            ? `${okIds.size} berhasil, ${gagal} gagal (lihat status).`
            : `${okIds.size} data berhasil diupload.`,
          gagal ? "error" : "success",
        );

        if (okIds.size) {
          try {
            await this.$wire.$refresh();
          } catch (e) {}
        }
      } catch (e) {
        this.say(
          "Gagal terhubung ke server. Data tetap aman di perangkat.",
          "error",
        );
      } finally {
        this.isSyncing = false;
      }
    },

    exportFile() {
      const items = this.pendingItems;
      if (!items.length)
        return this.say("Tidak ada data untuk di-download.", "error");

      const blob = new Blob(
        [
          JSON.stringify(
            {
              app: "turusan-offline",
              version: 1,
              exported_at: new Date().toISOString(),
              items,
            },
            null,
            2,
          ),
        ],
        { type: "application/json" },
      );
      const a = document.createElement("a");
      const stamp = new Date().toISOString().slice(0, 16).replace(/[:T]/g, "-");
      a.href = URL.createObjectURL(blob);
      a.download = `turusan-offline-nota-${this.parentId}-${stamp}.json`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 1000);
      this.say(`${items.length} data di-download.`, "success");
    },

    async importFile(event) {
      const file = event.target.files?.[0];
      event.target.value = "";
      if (!file) return;

      try {
        const json = JSON.parse(await file.text());
        if (json.app !== "turusan-offline" || !Array.isArray(json.items)) {
          return this.say(
            "File bukan backup turusan offline yang valid.",
            "error",
          );
        }

        const known = new Set(this.allItems.map((i) => i.uuid));
        let added = 0,
          dupe = 0,
          invalid = 0;

        for (const it of json.items) {
          const valid =
            it &&
            it.uuid &&
            it.parentId &&
            it.lahan_id &&
            it.jenis_kayu_id &&
            Number(it.diameter) > 0 &&
            Number(it.kuantitas) >= 1;
          if (!valid) {
            invalid++;
            continue;
          }
          if (known.has(it.uuid)) {
            dupe++;
            continue;
          }
          this.allItems.push({
            uuid: String(it.uuid),
            parentId: String(it.parentId),
            lahan_id: String(it.lahan_id),
            jenis_kayu_id: String(it.jenis_kayu_id),
            panjang: String(it.panjang ?? "130"),
            grade: String(it.grade ?? "1"),
            diameter: Number(it.diameter),
            kuantitas: Number(it.kuantitas),
            created_at: it.created_at || new Date().toISOString(),
            error: null,
          });
          known.add(it.uuid);
          added++;
        }

        if (!this.persist()) return;
        this.say(
          `Import selesai: ${added} baru, ${dupe} sudah ada, ${invalid} tidak valid.`,
          added ? "success" : "info",
        );
      } catch (e) {
        this.say("File tidak bisa dibaca.", "error");
      }
    },

    lahanLabel(id) {
      return this.labels.lahan[id] ?? `#${id}`;
    },
    jenisLabel(id) {
      return this.labels.jenis[id] ?? `#${id}`;
    },
    gradeLabel(g) {
      return String(g) === "1" ? "A" : String(g) === "2" ? "B" : "-";
    },
    kubikasi(i) {
      return (
        (Number(i.panjang) * i.diameter * i.diameter * i.kuantitas * 0.785) /
        1_000_000
      ).toFixed(4);
    },
  };
};
