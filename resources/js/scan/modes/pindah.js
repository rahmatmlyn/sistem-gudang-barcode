import { basePage } from '../page.js';

/** Putaway (scan rak tujuan lalu barang) dan transfer (scan asal, tujuan, lalu barang). */
export function putaway() {
    return {
        ...basePage({ type: 'PUT' }),
        lokasi: null,

        async handleScan(barcode) {
            const result = await this.scan({ barcode, location: this.lokasi });
            if (result.ok && result.data.jenis === 'lokasi') {
                this.lokasi = result.data.lokasi.kode;
            } else if (result.ok) {
                await this.reload();
            }
        },

        async ubah(baris, delta) {
            if ((await this.action('patch', `/api/scan/lines/${baris.id}`, { delta })).ok) {
                await this.reload();
            }
        },

        async selesai() {
            if (window.confirm(`Selesaikan ${this.doc.nomor}?`)
                && (await this.action('post', `/api/scan/documents/${this.doc.nomor}/complete`, {}, 'Putaway selesai')).ok) {
                this.lokasi = null;
                this.closeDoc();
            }
        },
    };
}

export function transfer() {
    return {
        ...putaway(),
        ...basePage({ type: 'TRF' }),
        asal: null,
        tujuan: null,

        async buat() {
            const result = await this.action('post', '/api/scan/documents', { type: 'TRF' }, 'Transfer dibuat');
            if (result.ok) {
                await this.openDoc(result.data.data.nomor);
            }
        },

        async handleScan(barcode) {
            const result = await this.scan({ barcode, location: this.asal, to_location: this.tujuan });
            if (result.ok && result.data.jenis === 'lokasi') {
                const kode = result.data.lokasi.kode;
                [this.asal, this.tujuan] = this.asal && !this.tujuan ? [this.asal, kode] : [kode, null];
            } else if (result.ok) {
                await this.reload();
            }
        },

        gantiLokasi() {
            this.asal = null;
            this.tujuan = null;
            this.refocus();
        },
    };
}
