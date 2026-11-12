# Prompt 1 — Mulai proyek

Salin teks di dalam blok berikut ke Claude Code saat pertama kali membuka folder ini.

```text
Baca CLAUDE.md terlebih dahulu, lalu baca docs/PRD.md dan docs/uat/checklist-uat.csv.
Sistem ini web saja, tanpa aplikasi mobile. Staf scan lewat browser dengan scanner
USB/Bluetooth mode keyboard wedge.

Folder ini belum berisi Laravel. Pasang Laravel di root folder ini tanpa menimpa
CLAUDE.md, README.md, docs/, tools/, dan database/dummy/ (misalnya buat di folder
sementara lalu pindahkan isinya, dan gabungkan .gitignore).

Buat rencana implementasi dan tunjukkan ke saya sebelum coding. Tunggu persetujuan saya.

M1. Setup: Laravel + Filament + PostgreSQL + Pest + Sanctum, 3 peran dengan hak akses.
    Staf Gudang hanya bisa membuka /scan, bukan panel Filament.
M2. Migrasi, model, dan seeder sesuai CLAUDE.md.
M3. StockService + test Pest (stok tidak minus saat proses bersamaan, draft tidak
    mengubah saldo, konversi barcode dus, ledger selalu cocok dengan saldo).
M4. Master data di Filament: barang dengan banyak barcode, lokasi bertingkat,
    import CSV/Excel dengan laporan baris gagal.
M5. Dokumen di Filament untuk Admin/Supervisor: membuat GRN, pesanan keluar,
    transfer, opname; memantau status; approval selisih opname.
M6. Endpoint POST /api/scan dan GET /api/stock, plus test-nya.
M7. Halaman scan /scan untuk Staf, sesuai bagian "Halaman scan" di CLAUDE.md dan
    tata letak di docs/wireframe/01-scan-penerimaan.html, 02-picking.html,
    03-stock-opname.html:
    - Menu: Penerimaan, Putaway, Picking, Packing, Transfer, Stock Opname
    - Penerimaan: pilih/buat GRN, scan barang, ubah qty dengan tombol − dan +,
      daftar barang diterima, tombol "Simpan Draft" dan "Selesai, lanjut Putaway"
    - Picking: kartu besar berisi lokasi yang harus didatangi, barang, dan qty;
      langkah 1 scan lokasi, langkah 2 scan barang; peringatan bila barang/lokasi
      salah; daftar item berikutnya diurutkan per lokasi
    - Stock Opname: hitung buta (qty sistem tidak ditampilkan), scan lokasi lalu
      scan barang, bisa dijeda dan dilanjutkan, tombol "Lokasi ini kosong"
    - Warna aksen #1B5FD0, font Inter
M8. Dashboard sesuai docs/wireframe/04-dashboard-admin.html: 4 kartu angka
    (SKU aktif, di bawah stok minimum, dokumen berjalan, selisih opname menunggu
    approval), tabel selisih opname, tabel stok di bawah minimum, aktivitas scan
    terbaru. Ditambah kartu stok, laporan stok per lokasi, export Excel/PDF,
    notifikasi stok minimum.
M9. Cetak label barcode barang dan lokasi ke PDF ukuran 50x30 mm.
M10. Command artisan untuk import data dummy dari database/dummy/ lalu
     verifikasi bahwa stock_balances cocok dengan total stock_movements.

Setelah setiap milestone: jalankan semua test, ringkas hasilnya beserta ID UAT
yang terpenuhi, dan tunggu konfirmasi saya sebelum lanjut.
```
