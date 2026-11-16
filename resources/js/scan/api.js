/**
 * Klien JSON untuk /api/*. Sesi Sanctum (cookie) + token CSRF dari <meta name="csrf-token">.
 * Scan diberi client_scan_id; bila jaringan putus, kiriman diulang dengan id yang sama sehingga server
 * tidak menggandakan qty (UAT-31). Respons HTTP 4xx/5xx adalah jawaban, bukan alasan untuk mengulang.
 */
export function uuid(cryptoImpl = globalThis.crypto) {
    if (typeof cryptoImpl?.randomUUID === 'function') {
        return cryptoImpl.randomUUID();
    }
    const bytes = cryptoImpl.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export function createApi({
    fetchImpl = (...args) => fetch(...args),
    csrf = document.querySelector('meta[name="csrf-token"]')?.content,
    uuid: makeId = () => uuid(),
    sleep = wait,
    retries = 8,
    onOffline = () => {},
    onOnline = () => {},
} = {}) {
    async function request(method, url, body) {
        const response = await fetchImpl(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, data };
    }

    async function withRetry(send) {
        for (let attempt = 0; ; attempt++) {
            try {
                const result = await send();
                onOnline();
                return result;
            } catch (error) {
                if (!(error instanceof TypeError) || attempt >= retries) {
                    throw error;
                }
                onOffline();
                await sleep(Math.min(500 * 2 ** attempt, 5000));
            }
        }
    }

    return {
        scan(body) {
            const payload = { ...body, client_scan_id: makeId() };
            return withRetry(() => request('POST', '/api/scan', payload));
        },
        get: (url) => withRetry(() => request('GET', url)),
        post: (url, body = {}) => withRetry(() => request('POST', url, body)),
        patch: (url, body = {}) => withRetry(() => request('PATCH', url, body)),
    };
}
