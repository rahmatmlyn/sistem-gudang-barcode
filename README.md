# Sistem Gudang Barcode

Sistem pergudangan berbasis web dengan scanner barcode (USB/Bluetooth, mode keyboard wedge). Setiap scan dicatat sebagai pergerakan stok di ledger.

## Isi folder

| Lokasi | Isi |
| --- | --- |
| `CLAUDE.md` | Konteks proyek yang dibaca otomatis oleh Claude Code setiap sesi |
| `docs/PRD.md` | Product Requirements Document (diagram dalam Mermaid) |
| `docs/Panduan_Sistem_Gudang_Barcode.pdf` | Panduan ide, hardware, dan stack |
| `docs/prompts/` | Prompt siap pakai untuk Claude Code |
| `docs/uat/` | Rencana uji dan checklist UAT (32 skenario) |
| `docs/wireframe/` | Wireframe layar scan dan dashboard (HTML, buka di browser) |
| `database/dummy/` | Data dummy CSV: 1.000 SKU, 246 lokasi, ±100.000 transaksi |
| `tools/generate_dummy.py` | Membuat ulang data dummy dengan jumlah berbeda |
| `tools/uji_scan_bersamaan.py` | Uji scan bersamaan ke server staging (UAT-26, UAT-27) |

## Cara mulai

1. Buka folder ini di VS Code dan pasang ekstensi yang direkomendasikan (muncul otomatis dari `.vscode/extensions.json`).
2. Siapkan PHP 8.3, Composer, Node.js, dan PostgreSQL di komputer.
3. Jalankan Claude Code di folder ini, lalu salin prompt dari `docs/prompts/01-mulai-proyek.md`.
4. Lanjutkan per milestone dengan prompt di `docs/prompts/02-prompt-lanjutan.md`.

## Membuat ulang data dummy

```bash
python tools/generate_dummy.py --items 1000 --movements 100000 --out database/dummy
```

Catatan: barcode EAN-13 di data dummy dibuat acak dengan check digit valid, hanya untuk pengujian.
