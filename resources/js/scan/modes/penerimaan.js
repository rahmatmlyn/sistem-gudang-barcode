import { basePage } from '../page.js';

/** Penerimaan (GRN, wireframe 01): scan barang, tombol − / +, selesai lalu lanjut putaway. */
export function penerimaan() {
    return {
        ...basePage({ type: 'GRN' }),
        baru: { partner: '', reference: '' },

        async buat() {
            const result = await this.action('post', '/api/scan/documents', { type: 'GRN', ...this.baru }, 'Penerimaan dibuat');
            if (result.ok) {
                this.baru = { partner: '', reference: '' };
                await this.openDoc(result.data.data.nomor);
            }
        },

        async handleScan(barcode) {
            if ((await this.scan({ barcode })).ok) {
                await this.reload();
            }
        },

        async ubah(baris, delta) {
            if ((await this.action('patch', `/api/scan/lines/${baris.id}`, { delta })).ok) {
                await this.reload();
            }
        },

        async selesai() {
            if (!window.confirm(`Selesaikan ${this.doc.nomor}? Stok masuk ke area terima.`)) {
                return;
            }
            const done = await this.action('post', `/api/scan/documents/${this.doc.nomor}/complete`, {}, 'Penerimaan selesai');
            if (!done.ok) {
                return;
            }
            const put = await this.action('post', `/api/scan/documents/${this.doc.nomor}/putaway`, {});
            if (put.ok) {
                window.location.href = `/scan/putaway?dokumen=${encodeURIComponent(put.data.data.nomor)}`;
            }
        },
    };
}
