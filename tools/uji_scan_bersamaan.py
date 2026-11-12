"""
Uji scan bersamaan (UAT-26 dan UAT-27) untuk API Sistem Gudang.

Skrip ini mengirim banyak permintaan scan keluar secara serentak ke server staging,
lalu memeriksa bahwa stok tidak minus dan jumlah yang berkurang sama persis dengan
jumlah scan yang diterima server.

ASUMSI ENDPOINT (sesuaikan dengan API yang kamu buat):
  POST {BASE}/api/scan            body: {"document_no", "barcode", "location", "qty", "type": "OUT"}
                                   sukses: HTTP 200/201, stok kurang: HTTP 409/422
  GET  {BASE}/api/stock?barcode=..&location=..   -> {"qty": <angka>}
Autentikasi: header "Authorization: Bearer <token>".

Pemakaian (jalankan HANYA di server staging, jangan di production):
  pip install httpx
  # UAT-26: stok tinggal 1, 10 perangkat scan bersamaan -> harus tepat 1 yang berhasil
  python uji_scan_bersamaan.py race --base https://staging.contoh.id --token XXX \
      --barcode 8990252016121 --location A-01-02 --doc OUT-UJI-001 --clients 10
  # UAT-27: 10 klien x 100 scan
  python uji_scan_bersamaan.py load --base https://staging.contoh.id --token XXX \
      --barcode 8990252016121 --location A-01-02 --doc OUT-UJI-002 --clients 10 --scans 100
"""

import argparse
import asyncio
import time

import httpx


async def get_stock(client, args):
    r = await client.get(f"{args.base}/api/stock", params={"barcode": args.barcode, "location": args.location})
    r.raise_for_status()
    return r.json()["qty"]


async def scan_once(client, args, results, latencies):
    payload = {"document_no": args.doc, "barcode": args.barcode, "location": args.location, "qty": 1, "type": "OUT"}
    t0 = time.perf_counter()
    try:
        r = await client.post(f"{args.base}/api/scan", json=payload)
        code = r.status_code
    except httpx.HTTPError as e:
        code = f"error: {type(e).__name__}"
    latencies.append(time.perf_counter() - t0)
    results.append(code)


async def worker(client, args, scans, start_gate, results, latencies):
    await start_gate.wait()  # semua klien mulai di saat yang sama
    for _ in range(scans):
        await scan_once(client, args, results, latencies)


async def run(args):
    headers = {"Authorization": f"Bearer {args.token}", "Accept": "application/json"}
    limits = httpx.Limits(max_connections=args.clients * 2)
    async with httpx.AsyncClient(headers=headers, timeout=15, limits=limits) as client:
        before = await get_stock(client, args)
        scans = 1 if args.mode == "race" else args.scans
        print(f"Stok awal: {before} | klien: {args.clients} | scan per klien: {scans}")
        if args.mode == "race" and before != 1:
            print("Peringatan: untuk mode race, atur stok di lokasi ini menjadi 1 terlebih dahulu.")

        gate, results, latencies = asyncio.Event(), [], []
        tasks = [asyncio.create_task(worker(client, args, scans, gate, results, latencies)) for _ in range(args.clients)]
        await asyncio.sleep(0.2)
        gate.set()
        await asyncio.gather(*tasks)

        after = await get_stock(client, args)

    ok = sum(1 for c in results if c in (200, 201))
    rejected = sum(1 for c in results if c in (409, 422))
    other = len(results) - ok - rejected
    avg = sum(latencies) / len(latencies) if latencies else 0
    p95 = sorted(latencies)[int(len(latencies) * 0.95) - 1] if latencies else 0

    print(f"Diterima: {ok} | ditolak (stok kurang): {rejected} | lainnya: {other}")
    print(f"Stok akhir: {after} | berkurang: {before - after}")
    print(f"Waktu respons rata-rata: {avg:.3f} dtk | p95: {p95:.3f} dtk")

    checks = [
        ("Stok tidak minus", after >= 0),
        ("Stok berkurang = jumlah scan diterima", before - after == ok),
        ("Tidak ada error lain", other == 0),
    ]
    if args.mode == "race":
        checks.append(("Tepat satu scan berhasil", ok == min(1, before)))
    for name, passed in checks:
        print(f"[{'LULUS' if passed else 'GAGAL'}] {name}")
    return all(p for _, p in checks)


def main():
    ap = argparse.ArgumentParser(description="Uji scan bersamaan")
    ap.add_argument("mode", choices=["race", "load"])
    ap.add_argument("--base", required=True, help="URL server staging, tanpa garis miring di akhir")
    ap.add_argument("--token", required=True)
    ap.add_argument("--barcode", required=True)
    ap.add_argument("--location", required=True)
    ap.add_argument("--doc", required=True, help="Nomor dokumen keluar untuk uji")
    ap.add_argument("--clients", type=int, default=10)
    ap.add_argument("--scans", type=int, default=100)
    args = ap.parse_args()
    passed = asyncio.run(run(args))
    raise SystemExit(0 if passed else 1)


if __name__ == "__main__":
    main()
