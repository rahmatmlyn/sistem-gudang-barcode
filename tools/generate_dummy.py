"""
Generator data dummy untuk Sistem Pergudangan Berbasis Barcode.

Menghasilkan CSV yang strukturnya mengikuti model data di PRD:
  items.csv, item_barcodes.csv, locations.csv, users.csv,
  stock_movements.csv (ledger) dan stock_balances.csv (saldo akhir).

Semua transaksi dibuat berurutan waktu dan dijaga agar stok tidak pernah minus,
sehingga data ini juga bisa dipakai untuk menguji kecocokan ledger vs saldo.

Pemakaian:
    python generate_dummy.py                      # default: 1.000 SKU, ±100.000 transaksi
    python generate_dummy.py --items 5000 --movements 300000 --out ./data

Catatan: barcode EAN-13 di sini dibuat acak dengan check digit yang valid,
hanya untuk pengujian. Jangan dipakai sebagai barcode produk sungguhan.
"""

import argparse
import csv
import os
import random
from datetime import datetime, timedelta

SEED = 2026

BASES = [
    "Beras Premium", "Beras Medium", "Gula Pasir", "Gula Merah", "Tepung Terigu",
    "Tepung Beras", "Minyak Goreng", "Mentega", "Margarin", "Kecap Manis",
    "Kecap Asin", "Saus Sambal", "Saus Tomat", "Mi Instan Goreng", "Mi Instan Kuah",
    "Bihun", "Kopi Bubuk", "Kopi Sachet", "Teh Celup", "Teh Bubuk",
    "Susu Kental Manis", "Susu UHT", "Susu Bubuk", "Biskuit Kelapa", "Wafer Cokelat",
    "Sarden Kaleng", "Kornet Kaleng", "Garam Dapur", "Merica Bubuk", "Kaldu Ayam",
    "Sabun Mandi", "Sabun Cuci Piring", "Deterjen Bubuk", "Deterjen Cair", "Pewangi Pakaian",
    "Sampo", "Pasta Gigi", "Tisu Wajah", "Air Mineral", "Minuman Isotonik",
]
SIZES = ["Kecil", "Sedang", "Besar", "Jumbo", "Ekonomis"]
VARIANTS = ["A", "B", "C", "D", "E"]
CATEGORIES = {
    "Sembako": BASES[0:13],
    "Makanan Instan": BASES[13:16] + BASES[25:30],
    "Minuman": BASES[16:23] + BASES[38:40],
    "Camilan": BASES[23:25],
    "Kebersihan": BASES[30:38],
}
UNITS = ["pcs", "pack", "botol", "kg"]


def category_of(base):
    for cat, bases in CATEGORIES.items():
        if base in bases:
            return cat
    return "Lainnya"


def ean13(rng):
    body = "899" + "".join(str(rng.randint(0, 9)) for _ in range(9))
    total = sum(int(d) * (3 if i % 2 else 1) for i, d in enumerate(body))
    return body + str((10 - total % 10) % 10)


def make_items(rng, n):
    items, barcodes, used = [], [], set()
    combos = [(b, s, v) for b in BASES for s in SIZES for v in VARIANTS]
    rng.shuffle(combos)
    while len(combos) < n:  # jika diminta lebih dari 1.000 SKU
        combos += [(b, s, f"{v}{len(combos)}") for b, s, v in combos[: n - len(combos)]]
    for i, (base, size, variant) in enumerate(combos[:n], start=1):
        sku = f"SKU-{i:05d}"
        items.append({
            "sku": sku,
            "name": f"{base} {size} Tipe {variant}",
            "category": category_of(base),
            "base_unit": rng.choice(UNITS),
            "min_stock": rng.choice([10, 20, 24, 30, 50, 80, 100]),
        })
        code = ean13(rng)
        while code in used:
            code = ean13(rng)
        used.add(code)
        barcodes.append({"barcode": code, "sku": sku, "type": "EAN-13", "unit": "satuan dasar", "factor": 1})
        if rng.random() < 0.6:
            factor = rng.choice([6, 12, 24, 40, 48])
            barcodes.append({"barcode": f"DUS-{sku}", "sku": sku, "type": "Code 128", "unit": "dus", "factor": factor})
    return items, barcodes


def make_locations():
    locs = [
        {"code": "RCV-01", "warehouse": "GU", "type": "area terima", "parent": "GU", "barcode": "LOC-RCV-01"},
        {"code": "SHP-01", "warehouse": "GU", "type": "area kirim", "parent": "GU", "barcode": "LOC-SHP-01"},
    ]
    bins = []
    for zone in "ABCD":
        locs.append({"code": zone, "warehouse": "GU", "type": "zona", "parent": "GU", "barcode": f"LOC-{zone}"})
        for rack in range(1, 11):
            rack_code = f"{zone}-{rack:02d}"
            locs.append({"code": rack_code, "warehouse": "GU", "type": "rak", "parent": zone, "barcode": f"LOC-{rack_code}"})
            for b in range(1, 6):
                bin_code = f"{rack_code}-{b:02d}"
                locs.append({"code": bin_code, "warehouse": "GU", "type": "bin", "parent": rack_code, "barcode": f"LOC-{bin_code}"})
                bins.append(bin_code)
    return locs, bins


def make_users():
    staff = [{"username": f"staf{i:02d}", "name": f"Staf Gudang {i:02d}", "role": "Staf Gudang"} for i in range(1, 9)]
    spv = [{"username": f"spv{i:02d}", "name": f"Supervisor {i:02d}", "role": "Supervisor"} for i in range(1, 3)]
    admin = [{"username": "admin01", "name": "Admin 01", "role": "Admin"}]
    return staff + spv + admin


def make_movements(rng, items, bins, target):
    skus = [it["sku"] for it in items]
    home = {sku: rng.choice(bins) for sku in skus}  # lokasi utama tiap barang
    balance = {}
    positive = []  # kunci (sku, lokasi) yang pernah positif; dibersihkan saat dipilih
    staff = [f"staf{i:02d}" for i in range(1, 9)]
    spv = ["spv01", "spv02"]
    rows, doc_seq = [], {"GRN": 0, "OUT": 0, "TRF": 0, "ADJ": 0}
    t = datetime(2026, 1, 1, 7, 0, 0)
    mid = 0

    def add(doc, mtype, sku, frm, to, qty, user):
        nonlocal mid, t
        mid += 1
        t += timedelta(seconds=rng.randint(5, 90))
        if t.hour >= 17:  # jam operasional 07.00-17.00
            t = (t + timedelta(days=1)).replace(hour=7, minute=0, second=0)
        if frm:
            balance[(sku, frm)] -= qty
        if to:
            key = (sku, to)
            if balance.get(key, 0) <= 0:
                positive.append(key)
            balance[key] = balance.get(key, 0) + qty
        rows.append({
            "id": mid, "document_no": doc, "type": mtype, "sku": sku,
            "from_location": frm or "", "to_location": to or "", "qty": qty,
            "user": user, "created_at": t.strftime("%Y-%m-%d %H:%M:%S"),
        })

    def pick_stock(exclude_rcv=True):
        for _ in range(50):
            if not positive:
                return None
            i = rng.randrange(len(positive))
            key = positive[i]
            if balance.get(key, 0) <= 0:
                positive[i] = positive[-1]
                positive.pop()
                continue
            if exclude_rcv and key[1] == "RCV-01":
                continue
            return key
        return None

    while len(rows) < target:
        r = rng.random()
        if r < 0.30 or len(positive) < 50:
            doc_seq["GRN"] += 1
            doc = f"GRN-2026-{doc_seq['GRN']:05d}"
            user = rng.choice(staff)
            lines = rng.sample(skus, rng.randint(2, 8))
            for sku in lines:
                qty = rng.randint(10, 120)
                add(doc, "GRN", sku, None, "RCV-01", qty, user)
            for sku in lines:  # putaway ke lokasi utama barang
                qty = balance[(sku, "RCV-01")]
                if qty > 0:
                    add(doc, "PUT", sku, "RCV-01", home[sku], qty, user)
        elif r < 0.85:
            doc_seq["OUT"] += 1
            doc = f"OUT-2026-{doc_seq['OUT']:05d}"
            user = rng.choice(staff)
            for _ in range(rng.randint(1, 6)):
                key = pick_stock()
                if not key:
                    break
                qty = rng.randint(1, max(1, min(balance[key], 30)))
                add(doc, "OUT", key[0], key[1], None, qty, user)
        elif r < 0.95:
            doc_seq["TRF"] += 1
            doc = f"TRF-2026-{doc_seq['TRF']:05d}"
            user = rng.choice(staff)
            key = pick_stock()
            if key:
                dest = rng.choice([b for b in rng.sample(bins, 3) if b != key[1]] or [home[key[0]]])
                qty = rng.randint(1, balance[key])
                add(doc, "TRF", key[0], key[1], dest, qty, user)
        else:
            doc_seq["ADJ"] += 1
            doc = f"ADJ-2026-{doc_seq['ADJ']:05d}"
            user = rng.choice(spv)
            key = pick_stock()
            if key:
                if rng.random() < 0.5:
                    add(doc, "ADJ", key[0], key[1], None, rng.randint(1, min(3, balance[key])), user)
                else:
                    add(doc, "ADJ", key[0], None, key[1], rng.randint(1, 3), user)
    return rows, balance


def write_csv(path, rows, fields):
    with open(path, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)


def main():
    ap = argparse.ArgumentParser(description="Generator data dummy sistem gudang")
    ap.add_argument("--items", type=int, default=1000)
    ap.add_argument("--movements", type=int, default=100000)
    ap.add_argument("--out", default=".")
    args = ap.parse_args()

    rng = random.Random(SEED)
    os.makedirs(args.out, exist_ok=True)

    items, barcodes = make_items(rng, args.items)
    locs, bins = make_locations()
    users = make_users()
    movements, balance = make_movements(rng, items, bins, args.movements)

    balances = [{"sku": s, "location": l, "qty": q} for (s, l), q in sorted(balance.items()) if q != 0]
    assert all(b["qty"] > 0 for b in balances), "Ada saldo minus: generator bermasalah"

    write_csv(os.path.join(args.out, "items.csv"), items, ["sku", "name", "category", "base_unit", "min_stock"])
    write_csv(os.path.join(args.out, "item_barcodes.csv"), barcodes, ["barcode", "sku", "type", "unit", "factor"])
    write_csv(os.path.join(args.out, "locations.csv"), locs, ["code", "warehouse", "type", "parent", "barcode"])
    write_csv(os.path.join(args.out, "users.csv"), users, ["username", "name", "role"])
    write_csv(os.path.join(args.out, "stock_movements.csv"), movements,
              ["id", "document_no", "type", "sku", "from_location", "to_location", "qty", "user", "created_at"])
    write_csv(os.path.join(args.out, "stock_balances.csv"), balances, ["sku", "location", "qty"])

    print(f"items: {len(items)}, barcodes: {len(barcodes)}, locations: {len(locs)}, "
          f"movements: {len(movements)}, balances: {len(balances)}")
    print(f"periode transaksi: {movements[0]['created_at']} s.d. {movements[-1]['created_at']}")


if __name__ == "__main__":
    main()
