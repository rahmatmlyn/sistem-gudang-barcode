/**
 * Input scan untuk scanner keyboard wedge: scanner mengetik barcode lalu Enter.
 * Scan identik yang datang < windowMs setelah scan terakhir yang diterima diabaikan (CLAUDE.md).
 */
export function createScanGate({ windowMs = 1000, now = () => Date.now() } = {}) {
    let lastCode = null;
    let lastAt = -Infinity;

    const normalize = (raw) => String(raw ?? '').replace(/^[\s\u0000]+|[\s\u0000]+$/g, '');

    return {
        normalize,
        accept(raw) {
            const code = normalize(raw);
            if (code === '') {
                return false;
            }
            const at = now();
            if (code === lastCode && at - lastAt < windowMs) {
                return false;
            }
            lastCode = code;
            lastAt = at;
            return true;
        },
    };
}

/**
 * Komponen Alpine untuk satu kolom scan yang selalu fokus.
 * Fokus diambil ulang saat blur dan setelah setiap aksi, kecuali saat $store.scan.paused (modal/isian lain terbuka).
 */
export function scanInput({ onScan, windowMs = 1000 } = {}) {
    return {
        value: '',
        gate: createScanGate({ windowMs }),
        init() {
            this.focus();
            this.$el.addEventListener('blur', () => setTimeout(() => this.focus(), 150));
            window.addEventListener('scan:refocus', () => this.focus());
            document.addEventListener('visibilitychange', () => this.focus());
        },
        focus() {
            const active = document.activeElement;
            const typingElsewhere = active && active !== this.$el && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName);
            if (!this.$store.scan.paused && !typingElsewhere && active !== this.$el) {
                this.$el.focus({ preventScroll: true });
            }
        },
        submit() {
            const code = this.gate.normalize(this.value);
            this.value = '';
            if (this.gate.accept(code)) {
                (onScan ?? ((barcode) => window.dispatchEvent(new CustomEvent('scan', { detail: barcode }))))(code);
            }
            this.focus();
        },
    };
}
