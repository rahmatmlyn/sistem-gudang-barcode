import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const kamera = vi.hoisted(() => ({ onDecode: null, stop: null }));

vi.mock('html5-qrcode', () => ({
    Html5Qrcode: class {
        async start(_camera, _config, onDecode) {
            kamera.onDecode = onDecode;
        }
        async stop() {
            kamera.stop();
        }
    },
}));

const { cameraScanner } = await import('../../resources/js/scan/camera.js');

function clock() {
    let now = 0;
    return { now: () => now, advance: (ms) => { now += ms; } };
}

describe('cameraScanner (mode terus menyala)', () => {
    let scans;
    let time;
    let component;

    beforeEach(async () => {
        scans = [];
        time = clock();
        kamera.stop = vi.fn();
        vi.stubGlobal('window', new EventTarget());
        window.addEventListener('scan', (event) => scans.push(event.detail));
        component = Object.assign(cameraScanner({ now: time.now }), {
            $store: { scan: { paused: false } },
            $nextTick: async () => {},
        });
        await component.start();
    });

    afterEach(() => vi.unstubAllGlobals());

    it('keeps the camera open after a successful read', () => {
        kamera.onDecode('8991000000011');

        expect(scans).toEqual(['8991000000011']);
        expect(kamera.stop).not.toHaveBeenCalled();
        expect(component.open).toBe(true);
        expect(component.terakhir).toBe('8991000000011');
    });

    it('sends each barcode once while it stays in view, and different barcodes immediately', () => {
        kamera.onDecode('LOC-A-01-01');
        time.advance(100);
        kamera.onDecode('LOC-A-01-01');
        time.advance(100);
        kamera.onDecode('8991000000011');
        time.advance(100);
        kamera.onDecode('8991000000011');

        expect(scans).toEqual(['LOC-A-01-01', '8991000000011']);
    });

    it('closing the camera resumes the scan input', async () => {
        await component.stop();

        expect(kamera.stop).toHaveBeenCalledOnce();
        expect(component.open).toBe(false);
        expect(component.$store.scan.paused).toBe(false);
    });
});
