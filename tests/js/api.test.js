import { describe, expect, it, vi } from 'vitest';
import { createApi, uuid } from '../../resources/js/scan/api.js';

function response(status, body) {
    return { ok: status >= 200 && status < 300, status, json: async () => body };
}

describe('createApi', () => {
    it('retries with same client_scan_id', async () => {
        const fetchImpl = vi.fn()
            .mockRejectedValueOnce(new TypeError('Failed to fetch'))
            .mockRejectedValueOnce(new TypeError('Failed to fetch'))
            .mockResolvedValueOnce(response(200, { status: 'ok' }));
        const offline = vi.fn();
        const api = createApi({ fetchImpl, csrf: 't', sleep: async () => {}, onOffline: offline, uuid: () => 'id-1' });

        const result = await api.scan({ type: 'GRN', document_no: 'GRN-1', barcode: 'B' });

        expect(result).toEqual({ ok: true, status: 200, data: { status: 'ok' } });
        expect(fetchImpl).toHaveBeenCalledTimes(3);
        const ids = fetchImpl.mock.calls.map(([, init]) => JSON.parse(init.body).client_scan_id);
        expect(ids).toEqual(['id-1', 'id-1', 'id-1']);
        expect(offline).toHaveBeenCalledTimes(2);
    });

    it('does not retry a rejected scan (HTTP error is an answer, not a network failure)', async () => {
        const fetchImpl = vi.fn().mockResolvedValue(response(409, { status: 'gagal', kode: 'STOK_KURANG' }));
        const api = createApi({ fetchImpl, csrf: 't', sleep: async () => {} });

        const result = await api.scan({ type: 'OUT', document_no: 'OUT-1', barcode: 'B' });

        expect(result.ok).toBe(false);
        expect(result.data.kode).toBe('STOK_KURANG');
        expect(fetchImpl).toHaveBeenCalledTimes(1);
    });

    it('sends CSRF token and JSON headers with the session cookie', async () => {
        const fetchImpl = vi.fn().mockResolvedValue(response(200, {}));
        const api = createApi({ fetchImpl, csrf: 'csrf-123', sleep: async () => {} });

        await api.patch('/api/scan/lines/1', { delta: 1 });

        const [url, init] = fetchImpl.mock.calls[0];
        expect(url).toBe('/api/scan/lines/1');
        expect(init.method).toBe('PATCH');
        expect(init.credentials).toBe('same-origin');
        expect(init.headers['X-CSRF-TOKEN']).toBe('csrf-123');
        expect(init.headers.Accept).toBe('application/json');
    });
});

describe('uuid', () => {
    it('produces RFC 4122 v4 ids without crypto.randomUUID (plain HTTP on the warehouse LAN)', () => {
        const id = uuid({ getRandomValues: (bytes) => bytes.fill(7) });

        expect(id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    });
});
