import { basePage } from '../page.js';

/**
 * Stock opname hitung buta (wireframe 03): qty sistem tidak pernah dikirim server ke halaman ini.
 * Progres per lokasi tersimpan di server, jadi bisa dijeda dan dilanjutkan (UAT-15).
 */
export function opname() {
    return {
        ...basePage({ type: 'OPN' }),
        lokasi: null,

        lokasiSelesai() {
            return (this.doc?.lokasi ?? []).filter((row) => row.status !== 'belum').length;
        },
        lokasiBelum() {
            return (this.doc?.lokasi ?? []).filter((row) => row.status === 'belum');
        },
        barisLokasi() {
            return (this.doc?.baris ?? []).filter((baris) => baris.lokasi_asal === this.lokasi);
        },

        async handleScan(barcode) {
            const result = await this.scan({ barcode, location: this.lokasi });
            if (result.ok && result.data.jenis === 'lokasi') {
                this.lokasi = result.data.lokasi.kode;
            }
            if (result.ok) {
                await this.reload();
            }
        },

        async ubah(baris, delta) {
            if ((await this.action('patch', `/api/scan/lines/${baris.id}`, { delta })).ok) {
                await this.reload();
            }
        },

        async simpanLokasi(kosong) {
            if (kosong && !window.confirm(`Tandai ${this.lokasi} kosong?`)) {
                return;
            }
            const url = `/api/scan/opname/${this.doc.nomor}/locations/${encodeURIComponent(this.lokasi)}`;
            if ((await this.action('post', url, { kosong }, `Lokasi ${this.lokasi} disimpan`)).ok) {
                this.lokasi = null;
                await this.reload();
            }
        },

        async ajukan() {
            if (window.confirm('Ajukan hasil opname ke supervisor?')
                && (await this.action('post', `/api/scan/documents/${this.doc.nomor}/complete`, {}, 'Opname diajukan')).ok) {
                this.closeDoc();
            }
        },
    };
}
