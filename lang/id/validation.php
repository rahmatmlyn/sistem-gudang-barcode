<?php

/*
 * Pesan validasi Bahasa Indonesia (APP_LOCALE=id). Aturan yang tidak ada di sini
 * jatuh ke bahasa Inggris (APP_FALLBACK_LOCALE=en).
 */

return [
    'accepted' => ':Attribute harus disetujui.',
    'alpha_dash' => ':Attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'array' => ':Attribute harus berupa daftar.',
    'boolean' => ':Attribute harus bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'distinct' => ':Attribute tidak boleh kembar.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'exists' => ':Attribute yang dipilih tidak ditemukan.',
    'file' => ':Attribute harus berupa file.',
    'gt' => [
        'numeric' => ':Attribute harus lebih dari :value.',
    ],
    'gte' => [
        'numeric' => ':Attribute minimal :value.',
    ],
    'image' => ':Attribute harus berupa gambar.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'max' => [
        'array' => ':Attribute maksimal berisi :max item.',
        'file' => ':Attribute maksimal :max kilobyte.',
        'numeric' => ':Attribute maksimal :max.',
        'string' => ':Attribute maksimal :max karakter.',
    ],
    'mimes' => ':Attribute harus berupa file bertipe: :values.',
    'mimetypes' => ':Attribute harus berupa file bertipe: :values.',
    'min' => [
        'array' => ':Attribute minimal berisi :min item.',
        'file' => ':Attribute minimal :min kilobyte.',
        'numeric' => ':Attribute minimal :min.',
        'string' => ':Attribute minimal :min karakter.',
    ],
    'numeric' => ':Attribute harus berupa angka.',
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah dipakai.',
    'uploaded' => ':Attribute gagal diunggah.',

    'attributes' => [
        'sku' => 'SKU',
        'name' => 'Nama',
        'category' => 'Kategori',
        'base_unit' => 'Satuan dasar',
        'min_stock' => 'Stok minimum',
        'barcode' => 'Barcode',
        'type' => 'Tipe',
        'unit' => 'Satuan',
        'factor' => 'Faktor konversi',
        'code' => 'Kode',
        'warehouse' => 'Gudang',
        'parent' => 'Induk',
        'username' => 'Username',
        'password' => 'Password',
    ],
];
