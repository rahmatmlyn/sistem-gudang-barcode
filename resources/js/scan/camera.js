/**
 * Cadangan scan lewat kamera (html5-qrcode) bila tidak ada scanner. Hasil dikirim lewat event "scan" yang sama.
 * Kamera browser butuh HTTPS (atau localhost).
 */
export function cameraScanner() {
    return {
        open: false,
        error: '',
        scanner: null,
        async start() {
            this.error = '';
            this.open = true;
            this.$store.scan.paused = true;
            await this.$nextTick();
            try {
                const { Html5Qrcode } = await import('html5-qrcode');
                this.scanner = new Html5Qrcode('kamera-scan');
                await this.scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 260, height: 160 } }, (text) => {
                    window.dispatchEvent(new CustomEvent('scan', { detail: text }));
                    this.stop();
                });
            } catch (error) {
                this.error = window.isSecureContext
                    ? 'Kamera tidak bisa dibuka. Izinkan akses kamera di browser.'
                    : 'Kamera hanya bisa dipakai lewat HTTPS.';
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
