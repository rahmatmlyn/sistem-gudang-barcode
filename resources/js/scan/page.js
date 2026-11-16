import { createApi } from './api.js';
import { createFeedback } from './feedback.js';

const JUDUL_GAGAL = {
    BARCODE_TIDAK_DIKENAL: 'Barcode tidak dikenal',
    LOKASI_SALAH: 'Lokasi salah',
    BARANG_SALAH: 'Barang tidak sesuai',
    STOK_KURANG: 'Stok kurang',
    MELEBIHI_PESANAN: 'Melebihi pesanan',
    QTY_TIDAK_COCOK: 'Qty tidak cocok',
    DOKUMEN_TIDAK_AKTIF: 'Dokumen tidak aktif',
    DOKUMEN_TIDAK_DIKENAL: 'Dokumen tidak ditemukan',
};

/**
 * Perilaku bersama semua layar scan: pilih dokumen, antrean scan berurutan, kartu hijau/oranye + nada.
 * Mode menambahkan handleScan(barcode) dan aksi khususnya.
 */
export function basePage({ type, listType = type }) {
    return {
        type,
        listType,
        docs: [],
        doc: null,
        loading: false,
        card: null,
        queue: Promise.resolve(),
        api: null,
        feedback: null,

        init() {
            this.api = createApi({
                onOffline: () => { this.$store.scan.online = false; },
                onOnline: () => { this.$store.scan.online = true; },
            });
            this.feedback = createFeedback();
            const nomor = new URLSearchParams(window.location.search).get('dokumen');
            nomor ? this.openDoc(nomor) : this.loadDocs();
        },

        async loadDocs() {
            this.loading = true;
            const result = await this.api.get(`/api/scan/documents?type=${this.listType}`);
            this.docs = result.data.data ?? [];
            this.loading = false;
        },

        async openDoc(nomor) {
            const result = await this.api.get(`/api/scan/documents/${encodeURIComponent(nomor)}`);
            if (!result.ok) {
                this.fail('Dokumen tidak ditemukan', `Nomor ${nomor} tidak ada.`);
                return this.loadDocs();
            }
            this.doc = result.data.data;
            this.card = null;
            window.history.replaceState(null, '', `?dokumen=${encodeURIComponent(this.doc.nomor)}`);
            this.refocus();
        },

        async reload() {
            const result = await this.api.get(`/api/scan/documents/${encodeURIComponent(this.doc.nomor)}`);
            if (result.ok) {
                this.doc = result.data.data;
            }
        },

        closeDoc() {
            this.doc = null;
            window.history.replaceState(null, '', window.location.pathname);
            this.loadDocs();
        },

        onScan(barcode) {
            this.queue = this.queue
                .then(() => (this.doc ? this.handleScan(barcode) : this.openDoc(barcode)))
                .catch(() => this.fail('Gagal mengirim', 'Periksa koneksi lalu scan ulang.'))
                .finally(() => this.refocus());
        },

        async scan(body) {
            const result = await this.api.scan({ type: this.type, document_no: this.doc.nomor, ...body });
            result.ok ? this.ok(result.data.jenis === 'lokasi' ? 'Lokasi discan' : 'Scan berhasil', result.data.pesan) : this.failFrom(result);
            return result;
        },

        async action(method, url, body, judulSukses) {
            const result = await this.api[method](url, body);
            result.ok ? (judulSukses && this.ok(judulSukses, '')) : this.failFrom(result);
            this.refocus();
            return result;
        },

        ok(judul, pesan) {
            this.card = { ok: true, judul, pesan };
            this.feedback.sukses();
        },

        fail(judul, pesan) {
            this.card = { ok: false, judul, pesan };
            this.feedback.gagal();
        },

        failFrom(result) {
            const data = result.data ?? {};
            this.fail(JUDUL_GAGAL[data.kode] ?? 'Gagal', data.pesan ?? data.message ?? `Kesalahan server (${result.status}).`);
        },

        refocus() {
            window.dispatchEvent(new Event('scan:refocus'));
        },

        /** Jumlah per barang dari baris dokumen: [{sku, nama, satuan, rencana, scan, pack}]. */
        perBarang() {
            const rows = new Map();
            for (const baris of this.doc?.baris ?? []) {
                const row = rows.get(baris.sku) ?? { sku: baris.sku, nama: baris.nama, satuan: baris.satuan, rencana: 0, scan: 0, pack: 0 };
                row.rencana += baris.qty_rencana;
                row.scan += baris.qty_scan;
                row.pack += baris.qty_pack;
                rows.set(baris.sku, row);
            }
            return [...rows.values()];
        },
    };
}
