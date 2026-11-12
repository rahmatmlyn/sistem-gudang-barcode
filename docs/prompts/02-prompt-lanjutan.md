# Prompt lanjutan per milestone

Gunakan satu milestone per sesi Claude Code agar konteks tidak penuh.

## Setelah rencana disetujui

```text
Rencana disetujui. Kerjakan M1 saja, jalankan test, lalu laporkan hasilnya.
```

Ganti M1 dengan milestone berikutnya di sesi selanjutnya.

## M3 — inti stok (kerjakan test lebih dulu)

```text
Kerjakan M3 dengan pendekatan test lebih dulu: tulis test Pest untuk setiap aturan
bisnis di CLAUDE.md, pastikan gagal, lalu implementasikan StockService sampai lulus.
Sertakan test dua proses yang mengurangi stok yang sama secara bersamaan.
```

## M10 — import data dummy

```text
Kerjakan M10. File CSV ada di database/dummy/. Setelah import, tampilkan jumlah
baris per tabel dan pastikan stock_balances cocok dengan ledger. Lalu buka halaman
kartu stok dan laporan dengan data ini, catat waktu muatnya (target < 5 detik).
```

## Uji scan bersamaan (setelah M6 dan M7)

```text
Siapkan server lokal dan token Sanctum untuk pengujian, lalu jalankan
tools/uji_scan_bersamaan.py mode race dan load sesuai petunjuk di file tersebut.
Laporkan hasilnya untuk UAT-26 dan UAT-27, dan perbaiki bila ada yang GAGAL.
```

## Mencocokkan dengan checklist UAT

```text
Baca docs/uat/checklist-uat.csv. Untuk setiap skenario, sebutkan apakah sudah
tercakup oleh test otomatis, perlu diuji manual, atau belum diimplementasikan.
```

## Bila hasil melenceng

Perbarui CLAUDE.md (bukan hanya mengoreksi di chat), lalu minta:

```text
CLAUDE.md sudah saya perbarui di bagian <nama bagian>. Baca ulang dan sesuaikan
kode yang sudah ada.
```
