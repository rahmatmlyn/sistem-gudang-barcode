import { createScanGate } from './scanner.js';

/**
 * Cadangan scan lewat kamera (html5-qrcode) bila tidak ada scanner. Kamera tetap menyala sampai ditutup;
 * setiap barcode dikirim lewat event "scan" yang sama dengan scanner keyboard.
 * Barcode yang sama dihitung lagi hanya setelah hilang dari kamera selama 1 detik (gate sliding).
 * Kamera browser butuh HTTPS (atau localhost).
 */
export function cameraScanner({ now } = {}) {
    return {
        open: false,
        error: '',
        terakhir: '',
        scanner: null,
        gate: null,
        async start() {
            this.error = '';
            this.terakhir = '';
            this.open = true;
            this.gate = createScanGate({ sliding: true, ...(now ? { now } : {}) });
            this.$store.scan.paused = true;
            await this.$nextTick();
            try {
                const { Html5Qrcode } = await import('html5-qrcode');
                this.scanner = new Html5Qrcode('kamera-scan');
                await this.scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 260, height: 160 } }, (text) => this.read(text));
            } catch (error) {
                this.error = window.isSecureContext
                    ? 'Kamera tidak bisa dibuka. Izinkan akses kamera di browser.'
                    : 'Kamera hanya bisa dipakai lewat HTTPS.';
            }
        },
        read(text) {
            const code = this.gate.normalize(text);
            if (this.gate.accept(code)) {
                this.terakhir = code;
                window.dispatchEvent(new CustomEvent('scan', { detail: code }));
            }
        },
        async stop() {
            try {
                await this.scanner?.stop();
            } catch (error) {
                // kamera belum berjalan
            }
            this.scanner = null;
            this.open = false;
            this.$store.scan.paused = false;
            window.dispatchEvent(new Event('scan:refocus'));
        },
    };
}
