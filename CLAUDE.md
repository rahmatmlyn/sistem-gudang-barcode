# Sistem Gudang Barcode — Konteks Proyek

## Ringkasan
Sistem pergudangan berbasis barcode, SEPENUHNYA BERBASIS WEB. Tidak ada aplikasi mobile.
Staf melakukan scan lewat browser menggunakan scanner USB/Bluetooth mode keyboard wedge
(scanner mengetik barcode lalu Enter). Setiap scan menjadi pergerakan stok lewat StockService.

Dokumen acuan (baca bila perlu detail):
- docs/PRD.md — kebutuhan produk, user stories (US-xx), kebutuhan fungsional (FR-xx)
- docs/uat/checklist-uat.csv — 32 skenario uji (UAT-xx) yang harus lulus
- docs/wireframe/*.html — tata letak layar scan dan dashboard (buka di browser)

## Struktur folder
- Laravel dipasang di root folder ini.
- JANGAN menimpa atau menghapus: CLAUDE.md, README.md, docs/, tools/, database/dummy/.
- database/dummy/ berisi CSV data dummy (items, item_barcodes, locations, users,
  stock_movements, stock_balances) untuk import dan uji performa.
- tools/ berisi skrip Python pendukung (generator data dummy, uji scan bersamaan).

## Stack
- Laravel (versi stabil terbaru) + PHP 8.3
- Filament (versi stabil terbaru) untuk panel admin (Admin dan Supervisor)
- Halaman scan terpisah untuk Staf: Blade + Alpine.js, memanggil endpoint JSON
- Laravel Sanctum (sesi untuk halaman scan, token untuk skrip uji)
- PostgreSQL, Pest untuk testing
- html5-qrcode sebagai cadangan scan via kamera
- Antarmuka berbahasa Indonesia

## Peran pengguna
- Admin: master data, user, laporan, cetak label
- Supervisor: memantau dokumen, menyetujui selisih opname
- Staf Gudang: hanya halaman /scan (penerimaan, putaway, picking, packing, transfer, opname)

## Model data
- warehouses: kode, nama, alamat
- locations: kode, tipe (area terima/area kirim/zona/rak/bin), barcode, parent_id, warehouse_id
- items: sku, nama, kategori, satuan dasar, stok minimum
- item_barcodes: barcode (unik), item_id, jenis (EAN-13/Code 128/QR), satuan, faktor konversi
- documents: nomor, tipe (GRN/PUT/OUT/TRF/OPN/ADJ), status (draft/proses/selesai/batal), referensi, catatan, created_by
- document_lines: document_id, item_id, lokasi asal, lokasi tujuan, qty rencana, qty scan
- stock_movements: item_id, from_location_id, to_location_id, qty, tipe, document_line_id, user_id, created_at
- stock_balances: item_id, location_id, qty (unik per pasangan item–lokasi)
- activity_logs: user, aksi, data sebelum/sesudah, waktu

## Aturan bisnis (WAJIB)
1. Stok hanya berubah saat dokumen diselesaikan; draft tidak memengaruhi saldo.
2. Pengurangan stok ditolak jika saldo di lokasi tidak cukup. Stok tidak boleh minus.
3. stock_movements tidak pernah diedit atau dihapus. Koreksi = transaksi balik.
4. Barcode kemasan dikonversi ke satuan dasar dengan faktor konversi.
5. Selisih opname menjadi ADJ hanya setelah disetujui Supervisor, alasan wajib.
6. Nomor dokumen otomatis per tipe, format GRN-2026-00001.
7. Setiap perubahan stok dalam DB::transaction dengan lockForUpdate pada baris
   stock_balances, dan movement + balance ditulis dalam transaksi yang sama.

## Halaman scan (untuk Staf Gudang)
- Route /scan dengan layout sendiri: layar penuh, tombol minimal 44px, font besar,
  bisa dipakai di PC, tablet, dan browser PDA Android. Acuan: docs/wireframe/.
- Satu kolom input yang selalu fokus (autofocus + fokus ulang setelah blur dan setelah
  setiap aksi). Enter = kirim scan.
- Abaikan scan identik yang masuk kurang dari 1 detik setelah scan sebelumnya.
- Umpan balik: kartu hijau + nada tinggi untuk berhasil, kartu oranye + nada rendah
  untuk gagal (Web Audio API), beserta pesan yang jelas (barang tidak dikenal,
  lokasi salah, stok kurang).
- Semua scan lewat POST /api/scan → StockService. Target respons < 2 detik.
- GET /api/stock?barcode=&location= mengembalikan {"qty": n} untuk pengecekan.
  Kontrak ini dipakai tools/uji_scan_bersamaan.py: sukses HTTP 200/201,
  stok kurang HTTP 409 atau 422.
- Tombol "Scan pakai kamera" membuka html5-qrcode bila tidak ada scanner.

## Konvensi kode
- Logika stok hanya lewat App\Services\StockService (receive, putaway, issue, transfer, adjust).
  Resource Filament, controller, dan halaman scan tidak boleh mengubah stock_balances langsung.
- Setiap aturan bisnis punya test Pest, termasuk test stok tidak minus.
- Gunakan enum PHP untuk tipe dan status dokumen.
- Saat menyelesaikan fitur, sebutkan ID UAT yang dipenuhi (mis. UAT-05, UAT-12).
- Commit kecil per fitur dengan pesan yang jelas.
