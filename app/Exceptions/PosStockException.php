<?php

namespace App\Exceptions;

/**
 * Raised when a cart cannot be satisfied from the stock that is actually
 * available at the moment the order is written.
 *
 * In PosService::MODE_STRICT this aborts the order. In MODE_CLAMP it never
 * escapes -- the shortfall is recorded on the order as a stock conflict
 * instead, so the money the cashier took is never lost.
 */
class PosStockException extends PosException {}
