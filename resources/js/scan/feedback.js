/** Umpan balik scan: nada tinggi (~880 Hz, 120 ms) untuk berhasil, nada rendah (~220 Hz, 300 ms) untuk gagal. */
export function createFeedback({ AudioContextImpl = window.AudioContext ?? window.webkitAudioContext, vibrate } = {}) {
    let context = null;
    const buzz = vibrate ?? ((pattern) => navigator.vibrate?.(pattern));

    function tone(frequency, durationMs, type) {
        if (!AudioContextImpl) {
            return;
        }
        context ??= new AudioContextImpl();
        if (context.state === 'suspended') {
            context.resume();
        }
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = type;
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.25, context.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, context.currentTime + durationMs / 1000);
        oscillator.connect(gain).connect(context.destination);
        oscillator.start();
        oscillator.stop(context.currentTime + durationMs / 1000);
    }

    return {
        sukses() {
            tone(880, 120, 'sine');
            buzz(60);
        },
        gagal() {
            tone(220, 300, 'square');
            buzz([120, 60, 120]);
        },
    };
}
