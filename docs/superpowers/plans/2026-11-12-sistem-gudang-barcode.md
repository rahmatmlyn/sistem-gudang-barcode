# Sistem Gudang Barcode — Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sistem gudang berbasis web (Laravel + Filament + halaman scan Blade/Alpine) di mana setiap scan keyboard-wedge menjadi pergerakan stok yang konsisten, sesuai M1–M10.

**Architecture:** Satu aplikasi Laravel. Panel Filament `/admin` untuk Admin & Supervisor; halaman `/scan` (Blade + Alpine) untuk Staf yang memanggil endpoint JSON `/api/*`. Semua perubahan stok hanya lewat `App\Services\StockService` (ledger `stock_movements` + saldo `stock_balances` dalam satu `DB::transaction` dengan `lockForUpdate`). Orkestrasi scan ada di `App\Services\Scan\*` yang memanggil StockService.

**Tech Stack:** Laravel & Filament versi stabil terbaru saat `composer create-project` (dicek saat M1), PostgreSQL, Pest, Sanctum, Alpine.js + Vite, html5-qrcode, `@fontsource/inter`, picqer/php-barcode-generator, chillerlan/php-qrcode, barryvdh/laravel-dompdf, openspout/openspout (import/export Excel+CSV).

**Spec:** `CLAUDE.md`, `docs/PRD.md`, `docs/uat/checklist-uat.csv`, `docs/wireframe/*.html`, `tools/uji_scan_bersamaan.py` (kontrak API).

## Keputusan yang perlu disetujui

| No. | Keputusan | Usulan |
|---|---|---|
| K1 | **Rule 1 vs uji scan bersamaan.** Script & UAT-12/26 menuntut scan OUT langsung ditolak bila stok kurang dan `/api/stock` langsung berkurang, sementara aturan 1 menyatakan saldo baru berubah saat dokumen selesai. | **Reservasi terhitung.** Scan PUT/OUT/TRF menaikkan `document_lines.qty_scan` setelah mengecek `tersedia = stock_balances.qty − Σ qty_scan baris terbuka (draft/proses) dari lokasi itu`, di bawah `lockForUpdate` pada baris saldo. `stock_balances.qty` & ledger baru berubah saat dokumen diselesaikan (aturan 1 utuh). `GET /api/stock` mengembalikan `{"qty": tersedia, "saldo": n, "dipesan": n}` sehingga script lulus. Tanpa kolom tambahan di stock_balances. |
| K2 | **Dedupe < 1 detik.** Uji load (UAT-27) mengirim barcode sama 100× beruntun dari satu klien. | Dedupe **hanya di klien** (halaman scan), bukan di server. Server memakai `client_scan_id` opsional untuk idempoten (UAT-31). |
| K3 | **Versi PHP.** Terpasang PHP 8.5; CLAUDE.md minta 8.3. Composer belum ada. | `brew install composer`, jalankan dengan PHP 8.5 lokal, set `config.platform.php = "8.3.0"` di composer.json agar dependensi tetap kompatibel 8.3 (target produksi). Alternatif: `brew install php@8.3`. |
| K4 | **PostgreSQL.** Yang berjalan: 13.13 (sudah EOL). | Pakai PG 13 lokal untuk dev (DB `gudang` + `gudang_test`); produksi ≥ 16. |
| K5 | **Login.** users.csv hanya punya `username`. | Tambah kolom `username` (unik) + `role`; satu halaman `/login` (username + password) untuk semua peran; Admin/Supervisor → `/admin`, Staf → `/scan`. Password default user dummy: `password` (wajib ganti, hanya dev). |
| K6 | **Hak akses.** | Admin: semua kecuali approval opname. Supervisor: lihat master data, kelola dokumen, approval opname, laporan. Staf: hanya `/scan` + `/api/*` scan. Super Admin/tenant: di luar MVP. |
| K7 | **Tambahan skema di luar CLAUDE.md.** | `users.username, users.role`; `items.is_active`; `documents.submitted_at, completed_at, approved_by, approved_at, approval_reason, reversal_of_id`; `document_lines.qty_pack, qty_system`; tabel `document_sequences`, `opname_locations`, `scan_logs`. Status dokumen tetap 4 (draft/proses/selesai/batal); "menunggu approval" = OPN `proses` dengan `submitted_at` terisi. |
| K8 | **Qty integer** dalam satuan dasar; faktor konversi integer (data dummy semuanya bilangan bulat). | — |
| K9 | **Label ZPL/TSPL** (FR-06) dan **backup** (UAT-32) | Di luar M1–M10; M9 hanya PDF 50×30 mm. Backup dicatat sebagai runbook terpisah. |

## Global Constraints

- PHP 8.3 sebagai target platform; Laravel & Filament versi stabil terbaru; PostgreSQL; Pest.
- Jangan menimpa/menghapus `CLAUDE.md`, `README.md`, `docs/`, `tools/`, `database/dummy/`. `.gitignore` digabung, bukan ditimpa.
- Antarmuka berbahasa Indonesia. Warna aksen `#1B5FD0`, font Inter (self-host via `@fontsource/inter`, gudang bisa tanpa internet luar).
- Nomor dokumen `{TIPE}-{TAHUN}-{5 digit}`, mis. `GRN-2026-00001`.
- `stock_movements` tidak pernah di-UPDATE/DELETE (dijaga model + trigger PostgreSQL).
- Tidak ada kode di luar `StockService` yang menulis `stock_balances` / `stock_movements` (pengecualian tunggal: bulk import dummy M10, tetap dalam transaksi dan diverifikasi).
- Enum PHP untuk tipe & status dokumen. Tombol halaman scan ≥ 44px.
- `POST /api/scan`: sukses 200/201, stok kurang **409**, validasi/barang/lokasi salah **422**, barcode tak dikenal **404**. Target respons < 2 detik.
- Setiap milestone: semua test Pest lulus, sebutkan ID UAT, commit kecil per fitur, tunggu konfirmasi.

## Review Focus

1. **Scan terkirim ulang setelah koneksi putus (UAT-31)** — request dengan `client_scan_id` yang sama harus mengembalikan hasil pertama tanpa menambah qty lagi. Test: `it('tidak menggandakan scan dengan client_scan_id sama')` di M6.
2. **Dua dokumen diselesaikan bersamaan dengan pasangan item–lokasi yang tumpang tindih dalam urutan berbeda** — tidak boleh deadlock; kunci saldo selalu diurutkan `(item_id, location_id)`. Test proses paralel di M3.
3. **Lokasi dikirim sebagai kode (`A-01-02`, dari script) atau barcode (`LOC-A-01-02`, dari scanner), dengan spasi/CR sisa scanner** — keduanya harus dikenali setelah `trim`. Test di M6 (`BarcodeResolver`).
4. **Scan barcode dus saat sisa pesanan lebih kecil dari isi dus** (sisa 10, scan dus isi 24) — ditolak 422 "melebihi pesanan", tidak dicatat sebagian. Test di M6.
5. **Opname di lokasi yang barangnya sedang di-picking (sudah direservasi)** — `qty_system` snapshot = qty tersedia (fisik di rak), bukan saldo buku, agar selisih tidak palsu. Test di M5/M6 (`OpnameService`).

---

## Struktur file utama

```
app/Enums/            DocumentType (GRN,PUT,OUT,TRF,OPN,ADJ), DocumentStatus, LocationType, BarcodeType, UserRole, ScanMode (GRN,PUT,OUT,PACK,TRF,OPN)
app/Models/           Warehouse, Location, Item, ItemBarcode, Document, DocumentLine, StockMovement, StockBalance,
                      ActivityLog, ScanLog, OpnameLocation, DocumentSequence, User
app/Services/         StockService, DocumentNumberService, ActivityLogger, PickingAllocator, OpnameService, BarcodeResolver
app/Services/Scan/    ScanService (dispatcher), ScanHandler (interface), ReceiveScanHandler, PutawayScanHandler,
                      PickScanHandler, PackScanHandler, TransferScanHandler, OpnameScanHandler, ScanResult (DTO)
app/Exceptions/       StokTidakCukupException (→409), ScanDitolakException (→422), BarcodeTidakDikenalException (→404)
app/Http/Controllers/ Auth\LoginController, ScanPageController, Pdf\{LabelController, SuratJalanController}
app/Http/Controllers/Api/ ScanController, StockController, ScanDocumentController, OpnameLocationController
app/Http/Middleware/  EnsureRole
app/Filament/Resources/ Item, Location, Warehouse, User, GoodsReceipt, OutboundOrder, Transfer, StockOpname,
                      StockMovement (read-only), ActivityLog (read-only)
app/Filament/Pages/   Dashboard, KartuStok, StokPerLokasi
app/Filament/Widgets/ StatsOverview, SelisihOpnameTable, StokMinimumTable, AktivitasScanTable
app/Imports/          RowImporter (dasar + laporan baris gagal), ItemRowImporter, ItemBarcodeRowImporter, LocationRowImporter
app/Exports/          KartuStokExport, StokPerLokasiExport
app/Notifications/    StokMinimumNotification ; app/Listeners/KirimNotifikasiStokMinimum
app/Console/Commands/ ImportDummyData (gudang:import-dummy), VerifyStockBalances (gudang:verifikasi-saldo),
                      PrepareScanTest (gudang:siapkan-uji-scan)
resources/views/scan/ layout, menu, penerimaan, putaway, picking, packing, transfer, opname (.blade.php)
resources/js/scan/    scanner.js (fokus + dedupe), feedback.js (Web Audio), api.js (fetch + client_scan_id + retry), camera.js
resources/views/pdf/  label, surat-jalan, kartu-stok, stok-per-lokasi
tests/Feature/...     per milestone ; tests/Support/ambil_bersamaan.php (proses paralel)
```

## Kontrak inti (dipakai lintas milestone)

```php
// App\Services\StockService — satu-satunya penulis stock_balances & stock_movements
public function available(int $itemId, int $locationId): int;               // saldo − reservasi baris terbuka
public function reserve(DocumentLine $line, int $qtyBase): void;             // lock saldo asal, cek tersedia, qty_scan += qtyBase; StokTidakCukupException
public function release(DocumentLine $line, int $qtyBase): void;             // qty_scan −= qtyBase (≥ 0)
public function receive(Document $grn, User $by): void;                      // null → area terima, status selesai
public function putaway(Document $put, User $by): void;                      // area terima → rak/bin
public function issue(Document $out, User $by): void;                        // rak/bin → null; syarat qty_scan == qty_pack == qty_rencana
public function transfer(Document $trf, User $by): void;                     // asal → tujuan
public function adjust(Document $opn, User $approver, string $reason): Document; // buat ADJ selesai dari selisih
public function reverse(Document $doc, User $by, string $reason): Document;  // transaksi balik (ADJ, reversal_of_id)
```

Semua method penyelesai memakai satu helper privat `applyMovements(Document, array $movements, User)` yang: membuka `DB::transaction`, membuat baris saldo yang belum ada (`insertOrIgnore`), mengunci semua baris saldo terkait **urut `(item_id, location_id)`** dengan `lockForUpdate`, menolak bila ada hasil < 0, menulis movement + saldo, mengubah status dokumen, mencatat `activity_logs`, dan men-dispatch event `StokBerubah` (afterCommit) untuk notifikasi minimum.

`POST /api/scan` body: `{type: ScanMode, document_no, barcode, location?, qty? (default 1, kelipatan satuan barcode), client_scan_id?}`.
Respons sukses: `{status:"ok", pesan, jenis:"barang"|"lokasi", item?:{sku,nama}, qty_dasar, baris?:{id,qty_scan,qty_rencana,qty_pack}}`. Respons gagal: `{status:"gagal", kode, pesan}` dengan kode `BARCODE_TIDAK_DIKENAL|LOKASI_SALAH|BARANG_SALAH|MELEBIHI_PESANAN|STOK_KURANG|DOKUMEN_TIDAK_AKTIF`. Untuk mode OPN respons **tidak pernah** memuat qty sistem.

---

## M1 — Setup

**UAT:** UAT-23 (sebagian: Staf ditolak dari `/admin`).

### Task 1.1: Pasang Laravel tanpa menimpa berkas yang ada
- [ ] `brew install composer`; `git init` di root; commit awal berisi berkas yang sudah ada (CLAUDE.md, README.md, docs/, tools/, database/dummy/, .vscode/, .gitignore).
- [ ] `composer create-project laravel/laravel` ke `$SCRATCHPAD/laravel-baru`, lalu `rsync -a --ignore-existing` ke root (berkas yang ada tidak tertimpa); gabungkan `.gitignore` Laravel dengan isi `.gitignore` lama (dedupe baris); `README.md` Laravel dibuang.
- [ ] Verifikasi: `git status` tidak menunjukkan perubahan pada CLAUDE.md, README.md, docs/, tools/, database/dummy/. `php artisan --version` jalan.
- [ ] `.env` & `.env.example`: `DB_CONNECTION=pgsql`, `DB_DATABASE=gudang`, `APP_LOCALE=id`, `APP_TIMEZONE=Asia/Jakarta`, `QUEUE_CONNECTION=database`, `MAIL_MAILER=log`. Buat DB `gudang` dan `gudang_test`; `phpunit.xml` memakai `gudang_test`.
- [ ] composer.json: `config.platform.php = "8.3.0"` (K3). Commit `chore: pasang Laravel`.

### Task 1.2: Pest, Sanctum, Filament, aset frontend
- [ ] Pasang Pest (+ plugin Laravel), Sanctum (`php artisan install:api`), Filament (panel `admin` di `/admin`), `laravel-lang` id bila tersedia untuk Filament. npm: `alpinejs`, `html5-qrcode`, `@fontsource/inter`.
- [ ] Tema panel Filament: primary `#1B5FD0`, font Inter.
- [ ] `php artisan test` lulus (test bawaan). Commit `chore: pasang Pest, Sanctum, Filament`.

### Task 1.3: Peran dan hak akses
**Files:** `app/Enums/UserRole.php`, migrasi users (`username` unik, `role`), `app/Models/User.php`, `app/Http/Middleware/EnsureRole.php`, `app/Http/Controllers/Auth/LoginController.php`, `resources/views/auth/login.blade.php`, `routes/web.php`, `tests/Feature/Auth/AksesPeranTest.php`
**Produces:** `UserRole::{Admin, Supervisor, Staf}` (value: `admin|supervisor|staf`, label Indonesia), `User::canAccessPanel()` true hanya Admin/Supervisor, middleware alias `role:staf,supervisor,admin`.
- [ ] Test gagal dulu:
  - `it('mengarahkan staf ke /scan setelah login')` → redirect `/scan`
  - `it('mengarahkan admin dan supervisor ke /admin setelah login')`
  - `it('menolak staf membuka /admin')` → 403
  - `it('menolak tamu membuka /scan')` → redirect `/login`
  - `it('login memakai username')`
- [ ] Implementasi; panel Filament tanpa login sendiri (redirect ke `/login`); `/scan` sementara halaman kosong ber-middleware `auth` + `role:staf,supervisor,admin`.
- [ ] `php artisan test` lulus. Commit `feat: peran pengguna dan login`.

## M2 — Migrasi, model, seeder

**UAT:** fondasi (belum ada UAT langsung).

### Task 2.1: Migrasi + model + enum
**Files:** migrasi untuk semua tabel di "Struktur file", enum di `app/Enums`, model di `app/Models`, `tests/Feature/Model/SkemaTest.php`
- [ ] Kolom kunci (selain yang ada di CLAUDE.md): `locations.code` unik per warehouse, `locations.barcode` unik; `item_barcodes.barcode` unik; `stock_balances` unik `(item_id, location_id)` + check `qty >= 0`; `document_lines.qty_rencana, qty_scan, qty_pack, qty_system` (int); `documents.number` unik; `scan_logs(id, client_scan_id unik nullable, user_id, document_id, mode, barcode, location_id, hasil, kode, pesan, response jsonb, created_at)`; `opname_locations(document_id, location_id, status: belum|selesai|kosong, counted_by, counted_at)` unik `(document_id, location_id)`; `document_sequences(type, year, last_number)` unik `(type, year)`.
- [ ] Indeks: `stock_movements(item_id, created_at)`, `(from_location_id)`, `(to_location_id)`, `(document_line_id)`; `document_lines(item_id, from_location_id)`.
- [ ] Migrasi trigger PostgreSQL `stock_movements_immutable` (BEFORE UPDATE OR DELETE → RAISE EXCEPTION); model `StockMovement` juga melempar exception pada `updating/deleting`.
- [ ] Test: `it('menolak update dan delete stock_movements di level database')`, `it('menolak saldo negatif lewat check constraint')`, `it('relasi item punya banyak barcode')`, `it('lokasi bertingkat via parent_id')`.
- [ ] Commit `feat: skema database dan model`.

### Task 2.2: Nomor dokumen
**Files:** `app/Services/DocumentNumberService.php`, `tests/Feature/Services/DocumentNumberServiceTest.php`
**Produces:** `DocumentNumberService::next(DocumentType $type, ?CarbonInterface $at = null): string` — lock baris `document_sequences` (`lockForUpdate`), format `GRN-2026-00001`.
- [ ] Test: `it('membuat GRN-2026-00001 lalu GRN-2026-00002')`, `it('urutan terpisah per tipe dan per tahun')`.
- [ ] Commit `feat: nomor dokumen otomatis`.

### Task 2.3: Seeder
**Files:** `database/seeders/{DatabaseSeeder, UserSeeder, WarehouseLocationSeeder, ItemSeeder}.php`
- [ ] Seeder kecil untuk dev: 1 gudang `GU` "Gudang Utama", RCV-01, SHP-01, 2 zona × 2 rak × 3 bin, 20 barang (sebagian punya barcode dus), user `admin`, `supervisor`, `staf01` (password `password`). Data besar datang dari M10.
- [ ] `php artisan migrate:fresh --seed` sukses; `php artisan test` lulus. Commit `feat: seeder data awal`.

## M3 — StockService (test lebih dulu)

**UAT:** UAT-03, UAT-05, UAT-12, UAT-20 (logika), UAT-25, UAT-26 (otomatis), UAT-27 (sebagian).

### Task 3.1: Test aturan bisnis (harus gagal dulu)
**Files:** `tests/Feature/Stock/StockServiceTest.php`, `tests/Feature/Stock/LedgerInvariantTest.php`, helper `tests/Pest.php` → `assertLedgerCocok()` (untuk setiap pasangan item–lokasi: Σ masuk − Σ keluar == stock_balances.qty, dan tidak ada saldo tanpa ledger).
- [ ] `it('draft GRN tidak mengubah saldo')` — scan 3 baris, status draft → `stock_balances` kosong, `stock_movements` kosong.
- [ ] `it('receive menambah saldo area terima dan menulis movement')`.
- [ ] `it('putaway memindahkan dari area terima ke rak, total tetap')`.
- [ ] `it('reserve menolak bila tersedia kurang dan saldo tidak minus')` → `StokTidakCukupException`, `qty_scan` tidak berubah.
- [ ] `it('reservasi dokumen lain mengurangi qty tersedia')` — saldo 5, dokumen A reserve 4 → dokumen B reserve 2 ditolak.
- [ ] `it('issue mengurangi saldo hanya saat dokumen selesai')`.
- [ ] `it('transfer: asal berkurang, tujuan bertambah, total tetap')` (UAT-13 logika).
- [ ] `it('konversi barcode dus 40 menjadi 40 satuan dasar')` — lewat `ItemBarcode::toBaseQty(int $qty): int`.
- [ ] `it('adjust tanpa alasan ditolak')`, `it('adjust mencatat approver dan alasan pada dokumen ADJ')`.
- [ ] `it('reverse membuat transaksi balik tanpa mengubah movement lama')`.
- [ ] `it('ledger selalu cocok dengan saldo')` — rangkaian acak receive/putaway/transfer/issue/adjust (seed tetap), lalu `assertLedgerCocok()`.
- [ ] Jalankan: `php artisan test --filter=Stock` → FAIL (class/method belum ada).

### Task 3.2: Implementasi StockService
**Files:** `app/Services/StockService.php`, `app/Services/ActivityLogger.php`, `app/Exceptions/StokTidakCukupException.php`, `app/Events/StokBerubah.php`
**Produces:** kontrak di "Kontrak inti".
- [ ] Implementasi sesuai kontrak; `available()` = saldo − Σ `qty_scan` baris PUT/OUT/TRF berstatus draft/proses dengan `from_location_id` & `item_id` sama. `reserve()` mengunci baris saldo lalu baris `document_lines`.
- [ ] `php artisan test --filter=Stock` → PASS. Commit `feat: StockService dengan ledger dan reservasi`.

### Task 3.3: Test proses bersamaan (proses OS sungguhan)
**Files:** `tests/Feature/Stock/BersamaanTest.php` (pakai `DatabaseTruncation`, bukan `RefreshDatabase`, agar data terlihat oleh proses lain), `tests/Support/ambil_bersamaan.php` (bootstrap app env testing; argumen: line_id, qty, waktu mulai epoch-ms; tunggu sampai waktu mulai; panggil `reserve` atau `issue`; cetak `OK`/`STOK_KURANG`).
- [ ] `it('10 proses reserve stok tersisa 1: tepat satu berhasil')` — saldo 1 → 1 `OK`, 9 `STOK_KURANG`, tersedia 0, tidak ada saldo minus (UAT-26).
- [ ] `it('10 proses x 20 reserve: total cocok')` — saldo 150 → `OK` = 150, ditolak = 50, `assertLedgerCocok()` setelah issue (UAT-27).
- [ ] `it('dua dokumen dengan pasangan item-lokasi terbalik selesai bersamaan tanpa deadlock')` (Review Focus 2).
- [ ] PASS. Commit `test: uji stok bersamaan`.

## M4 — Master data di Filament

**UAT:** UAT-18; FR-01–FR-04, UAT-24 (log perubahan master).

### Task 4.1: Resource master
**Files:** `app/Filament/Resources/{Warehouse,Location,Item,User}Resource*`, `tests/Feature/Filament/MasterDataTest.php`
- [ ] Item: form SKU/nama/kategori/satuan dasar/stok minimum/foto/aktif + Repeater relationship `barcodes` (barcode, jenis, satuan, faktor ≥ 1). Location: tipe, parent (select tersaring warehouse), barcode default `LOC-{code}`; tabel menampilkan path `A › A-01 › A-01-02`. User: hanya Admin.
- [ ] Observer master data → `ActivityLogger` (before/after).
- [ ] Test (Livewire Filament): `it('admin membuat barang dengan dua barcode')`, `it('barcode duplikat ditolak')`, `it('lokasi tidak boleh menjadi parent dirinya sendiri')`, `it('supervisor hanya bisa melihat master data')`, `it('perubahan barang tercatat di activity_logs')`.
- [ ] Commit `feat: master data Filament`.

### Task 4.2: Import CSV/Excel dengan laporan baris gagal
**Files:** `app/Imports/{RowImporter,ItemRowImporter,ItemBarcodeRowImporter,LocationRowImporter}.php`, header action "Import" di Item/Location resource, `tests/Feature/Import/ImportMasterTest.php`, fixture `tests/Fixtures/items-1000-5-salah.xlsx` (dibuat di test via openspout).
**Produces:** `RowImporter::import(string $path): ImportReport` dengan `ImportReport{berhasil:int, gagal: array<int $nomorBaris, string[] $pesan>}`; kolom sama dengan CSV dummy (`sku,name,category,base_unit,min_stock` dst.). Baris valid di-upsert per SKU/kode; baris gagal tidak menghentikan import.
- [ ] Test: `it('import 1000 baris dengan 5 salah: 995 masuk, 5 dilaporkan dengan nomor baris')` (UAT-18), `it('menerima csv dan xlsx')`, `it('lokasi dengan parent yang belum ada di baris lebih awal tetap terimport')`.
- [ ] Laporan gagal tampil di notifikasi Filament + tombol unduh CSV `baris,pesan`.
- [ ] Commit `feat: import master dari CSV/Excel`.

## M5 — Dokumen di Filament

**UAT:** UAT-07, UAT-16, UAT-17, UAT-23, UAT-24, UAT-25.

### Task 5.1: Resource dokumen
**Files:** `app/Filament/Resources/{GoodsReceipt,OutboundOrder,Transfer,StockOpname}Resource*` (model `Document` di-scope per tipe), `app/Services/PickingAllocator.php`, `tests/Feature/Documents/*`
**Produces:** `PickingAllocator::allocate(Document $out): void` — memecah baris pesanan ke bin yang punya qty tersedia, urut kode lokasi; gagal bila stok total kurang.
- [ ] GRN: referensi/supplier, catatan, baris (barang, qty rencana opsional). OUT: baris barang + qty, aksi "Rilis ke picking" (alokasi). TRF: asal, tujuan, barang, qty. OPN: pilih lokasi/zona (mengisi `opname_locations`).
- [ ] Tabel memantau status (badge draft/proses/selesai/batal, polling 10 dtk). Tidak ada aksi edit/hapus untuk dokumen `selesai`; aksi "Buat transaksi balik" (alasan wajib) → `StockService::reverse`. `StockMovementResource` read-only.
- [ ] Test: `it('daftar picking diurutkan per lokasi')` (UAT-07), `it('dokumen selesai tidak bisa diedit atau dihapus')` (UAT-25), `it('staf tidak bisa mengakses resource dokumen')` (UAT-23).
- [ ] Commit `feat: dokumen GRN, OUT, TRF, OPN di Filament`.

### Task 5.2: Approval selisih opname
**Files:** `app/Services/OpnameService.php`, aksi di `StockOpnameResource`, `tests/Feature/Documents/OpnameApprovalTest.php`
**Produces:** `OpnameService::finishLocation(Document $opn, Location $loc, User $by, bool $kosong): void` (snapshot `qty_system` = `available()` untuk semua barang di lokasi; barang tak di-scan → baris qty_scan 0), `submit(Document $opn, User $by): void`, `approve(Document $opn, User $supervisor, string $reason): Document` (→ `StockService::adjust`), `reject(Document $opn, User $supervisor, string $reason): void`.
- [ ] Tabel selisih per lokasi/barang: fisik, sistem, selisih (hanya di panel, tidak di `/scan`).
- [ ] Test: `it('approval membuat ADJ dengan alasan dan nama supervisor')` (UAT-16), `it('approval tanpa alasan ditolak')` (UAT-17), `it('admin dan staf tidak bisa approve')`, `it('qty_system memakai qty tersedia saat ada reservasi picking')` (Review Focus 5).
- [ ] Commit `feat: approval selisih opname`.

## M6 — API scan & stok

**UAT:** UAT-01, 02, 03, 08, 09, 10, 12, 26/27 (via script), 31 (server).

### Task 6.1: BarcodeResolver + ScanService + handler
**Files:** `app/Services/BarcodeResolver.php`, `app/Services/Scan/*`, `app/Exceptions/{ScanDitolakException,BarcodeTidakDikenalException}.php`
**Produces:** `BarcodeResolver::resolve(string $raw): ItemBarcode|Location` (trim, cari `item_barcodes.barcode`, lalu `locations.barcode`, lalu `locations.code`); `ScanService::handle(ScanMode $mode, Document $doc, string $barcode, ?string $location, int $qty, User $user, ?string $clientScanId): ScanResult`. Handler: GRN (qty_scan += qty dasar, dokumen → proses), PUT/TRF/OUT (validasi lokasi & barang lalu `StockService::reserve`), PACK (qty_pack ≤ qty_scan), OPN (lokasi harus bagian dokumen & belum selesai; qty_scan per barang–lokasi). Setiap panggilan menulis `scan_logs`.

### Task 6.2: Endpoint
**Files:** `routes/api.php`, `app/Http/Controllers/Api/*`, `app/Http/Requests/ScanRequest.php`, `app/Console/Commands/PrepareScanTest.php`, `tests/Feature/Api/{ScanApiTest,StockApiTest,ScanDocumentApiTest}.php`
- [ ] Route (`auth:sanctum` + `role:staf,supervisor,admin`; sesi untuk `/scan`, token Bearer untuk script): `POST /api/scan`, `GET /api/stock`, `GET /api/scan/documents?type=`, `POST /api/scan/documents` (buat GRN/TRF dari halaman scan), `GET /api/scan/documents/{document}`, `PATCH /api/scan/lines/{line}` (tombol − / +), `POST /api/scan/documents/{document}/draft`, `.../complete`, `.../putaway` (GRN selesai → buat PUT berisi baris GRN), `.../ship`, `POST /api/scan/opname/{document}/locations/{location}` (`{kosong: bool}`), `GET /api/scan/documents/{document}/surat-jalan` (PDF).
- [ ] Test (semua lewat HTTP):
  - `it('scan barang terdaftar menambah qty 1')` (UAT-01); `it('barcode tak dikenal 404 dan tidak ada baris tersimpan')` (UAT-02)
  - `it('scan dus menambah qty sesuai faktor')` (UAT-03)
  - `it('picking: barang salah 422 BARANG_SALAH, qty tetap')` (UAT-08); `it('picking: lokasi salah 422 LOKASI_SALAH')` (UAT-09)
  - `it('picking melebihi stok 409 STOK_KURANG')` (UAT-12); `it('scan dus melebihi sisa pesanan ditolak utuh')` (Review Focus 4)
  - `it('kirim ditolak bila qty packing tidak cocok')` (UAT-10); `it('kirim mengurangi stok dan surat jalan bisa diunduh')` (UAT-11)
  - `it('lokasi dikenali lewat kode atau barcode, dengan spasi sisa scanner')` (Review Focus 3)
  - `it('tidak menggandakan scan dengan client_scan_id sama')` (UAT-31, Review Focus 1)
  - `it('GET /api/stock mengembalikan qty tersedia')`; `it('respons scan OPN tidak memuat qty sistem')` (UAT-14)
  - `it('token staf bisa scan, tanpa token 401')`
- [ ] `gudang:siapkan-uji-scan {--stok=1} {--rencana=10}`: membuat dokumen OUT dirilis di satu lokasi dengan saldo diset lewat `StockService` (GRN+PUT) dan mencetak token Sanctum staf + nomor dokumen + argumen script.
- [ ] Jalankan `php artisan serve` + `python tools/uji_scan_bersamaan.py race ...` dan `load ...` → semua `[LULUS]` (laporkan angka, termasuk rata-rata waktu respons untuk UAT-28 lokal).
- [ ] Commit `feat: API scan dan stok`.

## M7 — Halaman scan `/scan`

**UAT:** UAT-01, 02, 04, 05, 06, 08, 09, 10, 11, 13, 14, 15, 31; UAT-30 manual dengan hardware.

### Task 7.1: Kerangka scan + perilaku input
**Files:** `resources/views/scan/layout.blade.php`, `menu.blade.php`, `resources/js/scan/{scanner,feedback,api,camera}.js`, `resources/css/scan.css`, `tests/Feature/Scan/ScanPageTest.php`, `tests/js/scanner.test.js` (Vitest)
**Produces:** komponen Alpine `scanInput({onScan})`: input selalu fokus (autofocus, fokus ulang pada `blur` dan setelah setiap aksi; tidak merebut fokus saat modal qty/teks terbuka), Enter = kirim, abaikan barcode identik < 1000 ms; `feedback.sukses()/gagal()` (Web Audio: nada tinggi ~880 Hz/120 ms, rendah ~220 Hz/300 ms, `navigator.vibrate` bila ada) + kartu hijau/oranye; `api.scan()` membuat `client_scan_id` (UUID), retry saat jaringan putus dengan id sama, banner "Koneksi terputus".
- [ ] Vitest: `ignores identical scan within 1000ms`, `accepts identical scan after 1000ms`, `accepts different barcode immediately`, `retries with same client_scan_id`.
- [ ] Pest: `it('menu menampilkan 6 mode')`, `it('halaman scan memakai layout sendiri tanpa navigasi Filament')`.
- [ ] Commit `feat: kerangka halaman scan`.

### Task 7.2–7.5: Layar per mode (acuan tata letak: `docs/wireframe/01..03`)
- [ ] **Penerimaan** (01): pilih/buat GRN, scan barang, daftar barang diterima dengan tombol − / + (≥ 44px), "Simpan Draft", "Selesai, lanjut Putaway". Commit.
- [ ] **Putaway + Transfer**: scan lokasi → scan barang; daftar sisa; "Selesai". Commit.
- [ ] **Picking + Packing** (02): kartu besar lokasi/barang/qty, langkah 1 scan lokasi, langkah 2 scan barang, peringatan barang/lokasi salah, daftar item berikutnya urut lokasi; packing: scan verifikasi, tombol "Kirim" aktif hanya bila semua cocok, unduh surat jalan. Commit.
- [ ] **Stock Opname** (03): pilih dokumen OPN, scan lokasi lalu barang, qty sistem tidak pernah tampil, "Lokasi ini kosong", "Selesai lokasi", progres lokasi tersimpan di server (lanjut setelah ditutup). Commit.
- [ ] Tombol "Scan pakai kamera" (html5-qrcode) di semua mode, memanggil `onScan` yang sama.
- [ ] Pest per layar memastikan view memuat elemen kunci (mis. `it('layar opname tidak memuat qty sistem')`); verifikasi manual di browser 390×844 dan desktop.

## M8 — Dashboard & laporan

**UAT:** UAT-20, 21, 22, 29.

- [ ] **Task 8.1 Dashboard** (`docs/wireframe/04-dashboard-admin.html`): `StatsOverview` (SKU aktif, di bawah stok minimum, dokumen berjalan, selisih opname menunggu approval), `SelisihOpnameTable`, `StokMinimumTable`, `AktivitasScanTable` (dari `scan_logs`). Test angka tiap kartu dengan data terkontrol. Commit.
- [ ] **Task 8.2 Kartu stok**: halaman `KartuStok` filter barang (wajib), lokasi, rentang tanggal; saldo awal (agregat sebelum tanggal mulai) + mutasi dengan saldo berjalan (window function) + saldo akhir; paginasi. Test: `it('saldo akhir = saldo awal + masuk − keluar')` (UAT-20). Commit.
- [ ] **Task 8.3 Stok per lokasi + export**: halaman `StokPerLokasi` (filter zona/lokasi/barang); export Excel (openspout) & PDF (dompdf) untuk kedua laporan sesuai filter. Test: `it('export xlsx dan pdf berisi baris sesuai filter')` (UAT-21). Commit.
- [ ] **Task 8.4 Notifikasi stok minimum**: listener `StokBerubah` → bila total stok barang melintasi batas (sebelum > min, sesudah ≤ min) kirim `StokMinimumNotification` (database Filament + email) ke Admin. Test: `it('notifikasi terkirim sekali saat stok mencapai minimum')` (UAT-22). Commit.

## M9 — Label barcode PDF 50×30 mm

**UAT:** UAT-19 (PDF; cetak fisik & keterbacaan diuji manual).

- [ ] `LabelController` + aksi massal "Cetak label" di Item & Location → PDF dompdf ukuran kertas 50×30 mm (141.73 × 85.04 pt), satu label per halaman. Barang: nama, SKU, barcode sesuai jenis (EAN-13/Code 128 via picqer, QR via chillerlan). Lokasi: kode + Code 128 `locations.barcode`.
- [ ] Test: `it('pdf label berukuran 50x30 mm dan satu halaman per label')` (cek MediaBox & jumlah halaman), `it('EAN-13 tidak valid ditolak dengan pesan')`. Commit.

## M10 — Import data dummy & verifikasi saldo

**UAT:** UAT-20, UAT-29 (data 100.000 transaksi).

- [ ] `gudang:import-dummy {--fresh} {--path=database/dummy}`: impor gudang `GU`, lokasi (urut parent), barang, barcode, user (password `password`), movement (buat dokumen `selesai` per `document_no`, baris diagregasi per dokumen–barang–asal–tujuan, `created_at` dipertahankan; insert per 1.000 baris), saldo dari `stock_balances.csv`; set `document_sequences` ke nomor maksimum per tipe/tahun. Tampilkan jumlah baris per tabel.
- [ ] `gudang:verifikasi-saldo`: bandingkan Σ ledger per item–lokasi dengan `stock_balances`; cetak selisih; exit code 1 bila ada selisih.
- [ ] Test (fixture CSV mini di `tests/Fixtures/dummy/`): `it('import mini lalu verifikasi lulus')`, `it('verifikasi gagal bila saldo dimanipulasi')`.
- [ ] Jalankan pada data penuh, catat waktu muat halaman kartu stok & laporan (target < 5 dtk). Commit.

---

## Cakupan UAT yang tidak otomatis

UAT-19 (cetak fisik), UAT-28 (diukur di jaringan gudang), UAT-30 (hardware scanner/PDA), UAT-32 (backup/restore — di luar M1–M10) diuji manual atau menjadi pekerjaan terpisah.
