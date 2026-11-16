<?php

namespace App\Imports;

use RuntimeException;

/** Baris valid secara format tetapi ditolak aturan bisnis; pesannya masuk laporan import. */
class ImportRowException extends RuntimeException {}
