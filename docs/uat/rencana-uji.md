# Rencana Uji

Checklist lengkap ada di `checklist-uat.csv` (32 skenario). Isi kolom Status dengan salah satu: Belum diuji, Lulus, Gagal, Diblokir.

| Aspek | Isi |
| --- | --- |
| Tujuan | Memastikan MVP memenuhi user stories dan kebutuhan P0 di PRD sebelum go-live |
| Ruang lingkup | Master data, label, inbound, outbound, transfer, stock opname, laporan, hak akses, dan kebutuhan non-fungsional |
| Di luar lingkup | Fitur P1 dan P2: multi-gudang, batch/expired, mode offline, multi-tenant, integrasi API |
| Lingkungan uji | Server staging terpisah dari production, berisi data dummy |
| Perangkat uji | 1 scanner USB, 1 PDA Android, 1 printer label thermal, 1 laptop dengan browser Chrome |
| Data uji | Data dummy master barang dan lokasi, serta minimal 100.000 transaksi untuk uji performa (`database/dummy/`) |
| Penguji | Developer untuk uji fungsional; staf gudang dan supervisor klien untuk UAT |
| Kriteria mulai | Semua kebutuhan P0 selesai dan lolos uji internal developer |
| Kriteria selesai | Semua skenario Lulus, tidak ada bug kritis terbuka, akurasi opname pilot minimal 98% |
| Penanganan bug | Setiap skenario Gagal dicatat di kolom Catatan, diperbaiki, lalu diuji ulang |
