<?php

namespace App\Exceptions;

use DomainException;

/** Barcode tidak bisa dicetak sebagai label (mis. check digit EAN-13 salah pada data lama). */
class LabelTidakValidException extends DomainException {}
