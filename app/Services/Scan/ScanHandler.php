<?php

namespace App\Services\Scan;

use App\Models\ItemBarcode;
use App\Models\Location;

/** Aturan scan per mode. Melempar StockRuleException untuk penolakan; tidak menulis saldo selain lewat StockService. */
interface ScanHandler
{
    public function onLocation(ScanInput $input, Location $location): ScanResult;

    public function onItem(ScanInput $input, ItemBarcode $barcode, int $qtyBase): ScanResult;
}
