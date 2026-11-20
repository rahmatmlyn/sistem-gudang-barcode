# Panduan: HP sebagai scanner untuk uji awal

Panduan ini untuk penguji yang belum memegang scanner USB atau PDA. HP Android disambung kabel USB ke laptop
penguji, lalu halaman `/scan` dibuka di Chrome HP dan barcode dibaca dengan kamera HP.

Cara ini hanya untuk uji awal. UAT-30 (kompatibilitas scanner) tetap diuji dengan scanner USB dan PDA sungguhan.

## Kenapa lewat kabel

Browser hanya mengizinkan kamera di halaman HTTPS atau `localhost`. Perintah `adb reverse` membuat HP membuka
server laptop sebagai `http://localhost:8000`, sehingga kamera langsung bisa dipakai tanpa sertifikat HTTPS dan
tanpa mengubah `.env`.

## Yang perlu disiapkan

| Kebutuhan | Keterangan |
|---|---|
| HP Android | Chrome versi terbaru, kamera belakang berfungsi |
| Kabel USB | Kabel data, bukan kabel yang hanya untuk mengisi daya |
| Laptop penguji | Aplikasi sudah terpasang dan database sudah terisi (lihat langkah 3) |
| Barcode untuk discan | Label cetak, atau label PDF yang dibuka di layar laptop (lihat langkah 6) |

## Langkah

### 1. Aktifkan USB debugging di HP

1. Buka **Setelan › Tentang ponsel**, lalu ketuk **Nomor build** 7 kali sampai muncul "Anda sekarang developer".
   Di beberapa merek letaknya di **Setelan › Tentang ponsel › Informasi software**.
2. Buka **Setelan › Sistem › Opsi developer**, lalu nyalakan **USB debugging**.

### 2. Pasang adb di laptop

macOS:

```bash
brew install --cask android-platform-tools
```

Windows: unduh **SDK Platform-Tools** dari situs Android Developers, ekstrak, lalu jalankan perintah dari folder itu.

Periksa:

```bash
adb version
```

### 3. Siapkan aplikasi di laptop

Lewati langkah ini bila aplikasi sudah pernah dijalankan dan database sudah terisi.

```bash
composer install
npm install
npm run build
php artisan migrate --seed
```

`--seed` membuat user uji (`admin`, `supervisor`, `staf01`, semuanya dengan sandi `password`), gudang, lokasi,
dan barang contoh. Untuk data lengkap 1.000 barang jalankan juga `php artisan gudang:import-dummy`.

Jalankan `npm run build` lagi setiap kali ada perubahan kode tampilan, karena HP memuat aset hasil build.

### 4. Sambungkan HP dan teruskan port

1. Sambungkan HP ke laptop dengan kabel USB.
2. Di HP muncul pertanyaan **Izinkan USB debugging?**. Centang **Selalu izinkan dari komputer ini**, lalu ketuk
   **Izinkan**.
3. Di laptop jalankan:

```bash
adb devices
```

HP harus tampil dengan status `device`. Bila tertulis `unauthorized`, buka kunci layar HP dan izinkan debugging.

4. Teruskan port 8000 HP ke laptop:

```bash
adb reverse tcp:8000 tcp:8000
```

5. Jalankan server di laptop (biarkan terminal ini tetap terbuka):

```bash
php artisan serve
```

### 5. Buka halaman scan di HP

1. Di Chrome HP buka `http://localhost:8000/login`.
2. Masuk sebagai `staf01` dengan sandi `password`.
3. Pilih mode, misalnya **Penerimaan**, lalu buat atau pilih dokumen.
4. Ketuk **Scan pakai kamera**. Saat Chrome meminta izin kamera, pilih **Izinkan**.

Kamera tetap menyala sampai **Tutup kamera** diketuk. Setiap barcode yang terbaca langsung dikirim, dan hasilnya
tampil di atas layar kamera: kartu hijau dengan nada tinggi bila berhasil, kartu oranye dengan nada rendah bila
gagal.

### 6. Barcode untuk discan

- **Label cetak:** masuk ke `http://localhost:8000/admin` di laptop sebagai `admin`, buka **Barang** atau
  **Lokasi**, centang beberapa baris, lalu pilih **Cetak label**.
- **Tanpa printer:** buka PDF label tadi di layar laptop, perbesar sampai barcode selebar kira-kira 5 cm, lalu
  arahkan kamera HP ke layar. Turunkan kecerahan layar bila barcode sulit terbaca karena silau.
- Barcode lokasi berbentuk `LOC-<kode>`, misalnya `LOC-A-01-01`.

## Cara scan dengan kamera

- Arahkan barcode ke dalam kotak di tengah layar, jarak 10–20 cm, dengan cahaya cukup.
- Satu barcode yang terus terlihat di kamera hanya dihitung **sekali**.
- Untuk menghitung barang yang sama lagi, jauhkan kamera sekitar **1 detik**, lalu arahkan kembali.
- Barcode yang berbeda (misalnya lokasi lalu barang) langsung diterima tanpa menunggu.
- Untuk barang yang sama dalam jumlah banyak, scan sekali lalu pakai tombol **+** di baris barang (penerimaan dan
  opname).

## Bila ada masalah

| Gejala | Penyebab dan solusi |
|---|---|
| `adb: command not found` | adb belum terpasang atau belum ada di PATH. Ulangi langkah 2 |
| `adb devices` kosong | Kabel hanya untuk mengisi daya, atau USB debugging belum menyala. Ganti kabel, periksa langkah 1. Di HP, ubah mode USB ke **Transfer file** |
| HP tidak bisa membuka `localhost:8000` | `adb reverse` hilang setelah kabel dicabut atau HP terkunci lama. Jalankan lagi `adb reverse tcp:8000 tcp:8000`. Periksa juga `php artisan serve` masih berjalan |
| "Kamera hanya bisa dipakai lewat HTTPS" | Halaman dibuka lewat alamat IP laptop, bukan `localhost`. Buka `http://localhost:8000` |
| "Kamera tidak bisa dibuka. Izinkan akses kamera" | Izin kamera ditolak. Di Chrome HP ketuk ikon di kiri alamat › **Izin** › **Kamera** › **Izinkan**, lalu muat ulang halaman |
| Barcode tidak terbaca | Barcode terlalu kecil, buram, atau silau. Dekatkan atau jauhkan perlahan, tambah cahaya, atau perbesar label di layar |
| Tampilan halaman rusak atau tidak berubah | Aset belum di-build. Jalankan `npm run build`, lalu muat ulang halaman di HP |
| Langsung kembali ke halaman login | Sesi berakhir atau halaman dibuka dengan alamat berbeda dari saat login. Selalu gunakan `http://localhost:8000` |

## Batasan

- **iPhone tidak bisa memakai cara ini.** Tidak ada `adb reverse` di iPhone, dan lewat alamat IP laptop kamera
  diblokir tanpa HTTPS. Gunakan HP Android.
- Panduan ini hanya untuk satu HP. Untuk uji beberapa perangkat sekaligus (UAT-26, UAT-27) gunakan server staging
  dan `tools/uji_scan_bersamaan.py`.
- Kecepatan baca kamera HP lebih rendah daripada scanner USB. Waktu respons yang diukur untuk UAT tetap memakai
  scanner atau PDA.
