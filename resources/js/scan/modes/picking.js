import { basePage } from '../page.js';

/** Picking dua langkah (wireframe 02): scan lokasi, lalu scan barang; daftar urut lokasi rak. */
export function picking() {
    return {
        ...basePage({ type: 'OUT' }),
        lokasi: null,

        sisa(baris) {
            return baris.qty_rencana - baris.qty_scan;
        },
        tugas() {
            return (this.doc?.baris ?? []).find((baris) => this.sisa(baris) > 0) ?? null;
        },
        berikutnya() {
            const tugas = this.tugas();
            return (this.doc?.baris ?? []).filter((baris) => baris !== tugas && this.sisa(baris) > 0).slice(0, 5);
        },
        selesaiBaris() {
            return (this.doc?.baris ?? []).filter((baris) => this.sisa(baris) <= 0).length;
        },
        lokasiBenar() {
            return this.tugas() !== null && this.lokasi === this.tugas().lokasi_asal;
        },

        async handleScan(barcode) {
            const result = await this.scan({ barcode, location: this.lokasi });
            if (result.ok && result.data.jenis === 'lokasi') {
                this.lokasi = result.data.lokasi.kode;
            } else if (result.ok) {
                await this.reload();
            }
        },

        async kosong() {
            const tugas = this.tugas();
            if (tugas && window.confirm(`Laporkan ${tugas.nama} di ${tugas.lokasi_asal} kosong? Rencana diubah menjadi ${tugas.qty_scan}.`)
                && (await this.action('patch', `/api/scan/lines/${tugas.id}`, { kosong: true }, 'Dilaporkan kosong')).ok) {
                await this.reload();
            }
        },
    };
}

/** Packing: scan ulang setiap barang; "Kirim" hanya aktif bila picking dan packing cocok dengan pesanan. */
export function packing() {
    return {
        ...basePage({ type: 'PACK', listType: 'OUT' }),
        terkirim: null,

        siapKirim() {
            const rows = this.perBarang();
            return rows.length > 0 && rows.every((row) => row.scan === row.rencana && row.pack === row.scan);
        },

        async handleScan(barcode) {
            if ((await this.scan({ barcode })).ok) {
                await this.reload();
            }
        },

        async kirim() {
            if (!window.confirm(`Kirim ${this.doc.nomor}? Stok berkurang.`)) {
                return;
            }
            const nomor = this.doc.nomor;
            if ((await this.action('post', `/api/scan/documents/${nomor}/complete`, {}, 'Pesanan dikirim')).ok) {
                this.terkirim = nomor;
                await this.reload();
            }
        },
    };
}
