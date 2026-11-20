import { describe, expect, it } from 'vitest';
import { createScanGate } from '../../resources/js/scan/scanner.js';

function clock(start = 0) {
    let now = start;
    return { now: () => now, advance: (ms) => { now += ms; } };
}

describe('createScanGate', () => {
    it('ignores identical scan within 1000ms', () => {
        const time = clock();
        const gate = createScanGate({ now: time.now });

        expect(gate.accept('8991000000011')).toBe(true);
        time.advance(999);
        expect(gate.accept('8991000000011')).toBe(false);
    });

    it('accepts identical scan after 1000ms', () => {
        const time = clock();
        const gate = createScanGate({ now: time.now });

        gate.accept('8991000000011');
        time.advance(1000);
        expect(gate.accept('8991000000011')).toBe(true);
    });

    it('accepts different barcode immediately', () => {
        const time = clock();
        const gate = createScanGate({ now: time.now });

        gate.accept('8991000000011');
        time.advance(10);
        expect(gate.accept('LOC-A-01-01')).toBe(true);
    });

    it('measures the window from the last accepted scan, not from ignored repeats', () => {
        const time = clock();
        const gate = createScanGate({ now: time.now });

        gate.accept('X');
        time.advance(600);
        expect(gate.accept('X')).toBe(false);
        time.advance(400);
        expect(gate.accept('X')).toBe(true);
    });

    it('ignores empty input and trims scanner leftovers', () => {
        const gate = createScanGate({ now: () => 0 });

        expect(gate.normalize('  LOC-A-01-01\r\n')).toBe('LOC-A-01-01');
        expect(gate.accept('   ')).toBe(false);
    });
});

describe('createScanGate sliding (kamera)', () => {
    it('counts a barcode held in view only once while it keeps being read', () => {
        const time = clock();
        const gate = createScanGate({ now: time.now, sliding: true });

        expect(gate.accept('8991000000011')).toBe(true);
        for (let frame = 0; frame < 30; frame++) {
            time.advance(100);
            expect(gate.accept('8991000000011')).toBe(false);
        }
    });

    it('accepts the same barcode again after it was out of view for 1000ms', () => {
        const time = clock();
        const gate = createScanGate({ now: time.now, sliding: true });

        gate.accept('X');
        time.advance(500);
        gate.accept('X');
        time.advance(999);
        expect(gate.accept('X')).toBe(false);
        time.advance(1000);
        expect(gate.accept('X')).toBe(true);
    });
});
